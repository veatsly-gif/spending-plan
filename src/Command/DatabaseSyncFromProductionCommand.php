<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:db:sync-from-prod',
    description: 'Dump production DB via SSH and restore it locally, excluding public.user table.'
)]
final class DatabaseSyncFromProductionCommand extends Command
{
    /**
     * Remote user id => local user id mapping.
     */
    private const USER_ID_MAP = [
        1 => 1,
        2 => 2,
        3 => 4,
        4 => 5,
    ];

    private const SYNC_TABLES = [
        'currency',
        'spending_plan',
        'income',
        'spend',
        'api_limit',
    ];

    private const RESTORE_ORDER = [
        'currency',
        'spending_plan',
        'income',
        'spend',
        'api_limit',
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Intentionally no CLI arguments: configuration comes from environment.
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sshTarget = $this->getRequiredEnv('PROD_SSH_TARGET');
        $remoteAppDir = $this->getRequiredEnv('PROD_APP_DIR');
        $sshKeyPath = $this->getRequiredEnv('PROD_SSH_KEY_PATH') ?? '/run/secrets/prod_ssh_key';

        if (null === $sshTarget || null === $remoteAppDir) {
            $io->error('Set PROD_SSH_TARGET and PROD_APP_DIR in .env before running this command.');

            return Command::INVALID;
        }
        if (!is_readable($sshKeyPath)) {
            $io->error(sprintf(
                'SSH key is not readable at "%s". Mount key into container and set PROD_SSH_KEY_PATH if needed.',
                $sshKeyPath
            ));

            return Command::FAILURE;
        }
        if (!$this->isSshAvailable()) {
            $io->error('SSH client is not available in this environment. Rebuild php image with openssh-client.');

            return Command::FAILURE;
        }

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');
        $dumpDir = $projectDir.'/var';
        if (!is_dir($dumpDir) && !mkdir($dumpDir, 0775, true) && !is_dir($dumpDir)) {
            $io->error(sprintf('Unable to create dump directory: %s', $dumpDir));

            return Command::FAILURE;
        }

        $dumpFile = sprintf(
            '%s/prod-db-%s.sql',
            $dumpDir,
            (new \DateTimeImmutable())->format('Ymd-His')
        );
        $mappedDumpFile = str_replace('.sql', '-mapped.sql', $dumpFile);

        $tableArgs = implode(' ', array_map(
            static fn (string $table): string => '--table=public.'.$table,
            self::SYNC_TABLES
        ));
        $remoteScript = sprintf(
            'cd %s && set -a && . ./.env && set +a && docker compose -f docker-compose.prod.yaml exec -T postgres pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --data-only --inserts --column-inserts --no-owner --no-privileges --exclude-table=public.user %s',
            escapeshellarg($remoteAppDir),
            $tableArgs
        );

        $dumpCommand = sprintf(
            'ssh -i %s -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new %s %s > %s',
            escapeshellarg($sshKeyPath),
            escapeshellarg($sshTarget),
            escapeshellarg($remoteScript),
            escapeshellarg($dumpFile)
        );

        $io->section('Creating production dump');
        $dumpExitCode = $this->runShellCommand($dumpCommand);
        if (0 !== $dumpExitCode) {
            $io->error(sprintf('Production dump failed with exit code %d.', $dumpExitCode));

            return Command::FAILURE;
        }

        $io->section('Applying local ID mapping in dump text');
        $rawDump = file_get_contents($dumpFile);
        if (false === $rawDump) {
            $io->error(sprintf('Unable to read dump file: %s', $dumpFile));

            return Command::FAILURE;
        }
        $mappedDump = $this->applyUserIdMappings($rawDump);
        $orderedMappedDump = $this->orderDumpInserts($mappedDump);
        if (false === file_put_contents($mappedDumpFile, $orderedMappedDump)) {
            $io->error(sprintf('Unable to write mapped dump file: %s', $mappedDumpFile));

            return Command::FAILURE;
        }

        $localDb = $this->getLocalDatabaseConfig();
        if (null === $localDb) {
            $io->error('DATABASE_URL is missing or invalid for local restore.');

            return Command::INVALID;
        }

        $truncateSql = sprintf(
            'TRUNCATE TABLE %s RESTART IDENTITY CASCADE;',
            implode(', ', array_map(static fn (string $table): string => 'public.'.$table, self::SYNC_TABLES))
        );
        $truncateFile = str_replace('.sql', '-truncate.sql', $dumpFile);
        if (false === file_put_contents($truncateFile, $truncateSql)) {
            $io->error(sprintf('Unable to write truncate SQL file: %s', $truncateFile));

            return Command::FAILURE;
        }

        $truncateCommand = $this->buildPsqlFileCommand($localDb, $truncateFile);
        $truncateExitCode = $this->runShellCommand($truncateCommand);
        if (0 !== $truncateExitCode) {
            $io->error(sprintf(
                'Truncate failed with exit code %d. Dump is kept at %s.',
                $truncateExitCode,
                $mappedDumpFile
            ));

            return Command::FAILURE;
        }

        $restoreCommand = $this->buildPsqlFileCommand($localDb, $mappedDumpFile);

        $io->section('Restoring dump into local database');
        $restoreExitCode = $this->runShellCommand($restoreCommand);
        if (0 !== $restoreExitCode) {
            $io->error(sprintf(
                'Restore failed with exit code %d. Dump is kept at %s.',
                $restoreExitCode,
                $mappedDumpFile
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Done. Dump saved to %s, mapped dump saved to %s, and restored into local DB. Table public.user was excluded.',
            $dumpFile,
            $mappedDumpFile
        ));

        return Command::SUCCESS;
    }

    private function runShellCommand(string $command): int
    {
        passthru($command, $exitCode);

        return (int) $exitCode;
    }

    private function isSshAvailable(): bool
    {
        return is_executable('/usr/bin/ssh') || is_executable('/bin/ssh');
    }

    private function getRequiredEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array{host:string,port:string,user:string,password:string,dbname:string}|null
     */
    private function getLocalDatabaseConfig(): ?array
    {
        $databaseUrl = $this->getRequiredEnv('DATABASE_URL');
        if (null === $databaseUrl) {
            return null;
        }

        $parts = parse_url($databaseUrl);
        if (false === $parts) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $user = $parts['user'] ?? null;
        $password = $parts['pass'] ?? '';
        $path = $parts['path'] ?? null;
        if (!is_string($host) || !is_string($user) || !is_string($path)) {
            return null;
        }

        $dbName = ltrim($path, '/');
        if ('' === $dbName) {
            return null;
        }

        $port = isset($parts['port']) ? (string) $parts['port'] : '5432';

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => (string) $password,
            'dbname' => $dbName,
        ];
    }

    /**
     * @param array{host:string,port:string,user:string,password:string,dbname:string} $db
     */
    private function buildPsqlFileCommand(array $db, string $sqlFile): string
    {
        return sprintf(
            'PGPASSWORD=%s psql -v ON_ERROR_STOP=1 -h %s -p %s -U %s -d %s -f %s',
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['user']),
            escapeshellarg($db['dbname']),
            escapeshellarg($sqlFile)
        );
    }

    private function applyUserIdMappings(string $dump): string
    {
        $lines = explode("\n", $dump);
        foreach ($lines as $index => $line) {
            $lines[$index] = $this->mapInsertLineUserId($line);
        }

        return implode("\n", $lines);
    }

    private function mapInsertLineUserId(string $line): string
    {
        if (!str_starts_with($line, 'INSERT INTO public.')) {
            return $line;
        }

        if (!preg_match('/^INSERT INTO public\.([a-z_]+) \((.+)\) VALUES \((.+)\);$/', $line, $matches)) {
            return $line;
        }

        $table = $matches[1];
        if (!in_array($table, ['income', 'spend'], true)) {
            return $line;
        }

        $columns = array_map('trim', explode(',', $matches[2]));
        $userIdIndex = array_search('user_id', $columns, true);
        if (false === $userIdIndex) {
            return $line;
        }

        $values = $this->splitSqlValues($matches[3]);
        if (!array_key_exists($userIdIndex, $values)) {
            return $line;
        }

        $rawUserId = trim($values[$userIdIndex]);
        if ('NULL' === strtoupper($rawUserId)) {
            return $line;
        }

        if (!ctype_digit($rawUserId)) {
            return $line;
        }

        $remoteUserId = (int) $rawUserId;
        $localUserId = self::USER_ID_MAP[$remoteUserId] ?? null;
        if (null === $localUserId) {
            // Unknown mapping for required-table rows: skip row.
            return '';
        }

        $values[$userIdIndex] = (string) $localUserId;

        return sprintf(
            'INSERT INTO public.%s (%s) VALUES (%s);',
            $table,
            implode(', ', $columns),
            implode(', ', $values)
        );
    }

    /**
     * Split SQL value list by commas while preserving quoted strings.
     *
     * @return list<string>
     */
    private function splitSqlValues(string $values): array
    {
        $result = [];
        $buffer = '';
        $inString = false;
        $length = strlen($values);

        for ($i = 0; $i < $length; ++$i) {
            $char = $values[$i];

            if ("'" === $char) {
                $buffer .= $char;
                if ($inString && $i + 1 < $length && "'" === $values[$i + 1]) {
                    $buffer .= "'";
                    ++$i;
                    continue;
                }
                $inString = !$inString;
                continue;
            }

            if (!$inString && ',' === $char) {
                $result[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ('' !== trim($buffer)) {
            $result[] = trim($buffer);
        }

        return $result;
    }

    private function orderDumpInserts(string $dump): string
    {
        $lines = explode("\n", $dump);
        $buckets = [];
        $otherLines = [];

        foreach (self::RESTORE_ORDER as $table) {
            $buckets[$table] = [];
        }

        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }

            if (preg_match('/^INSERT INTO public\.([a-z_]+) /', $line, $matches)) {
                $table = $matches[1];
                if (array_key_exists($table, $buckets)) {
                    $buckets[$table][] = $line;
                    continue;
                }
            }

            $otherLines[] = $line;
        }

        $ordered = [];
        if ([] !== $otherLines) {
            $ordered[] = implode("\n", $otherLines);
            $ordered[] = '';
        }

        foreach (self::RESTORE_ORDER as $table) {
            if ([] === $buckets[$table]) {
                continue;
            }
            $ordered[] = sprintf('-- %s', $table);
            $ordered[] = implode("\n", $buckets[$table]);
            $ordered[] = '';
        }

        return implode("\n", $ordered);
    }
}
