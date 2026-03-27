<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

final class NotificationTriggerConfigLoader
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<array{
     *     code: string,
     *     type: string,
     *     date: int|array<string, scalar>,
     *     triggers: list<string>,
     *     delivery_types: list<string>,
     *     template: string,
     *     frequency: array{
     *         mode: string,
     *         interval_seconds: int
     *     }
     * }>
     */
    public function load(): array
    {
        $directory = sprintf('%s/triggers/%s', $this->projectDir, $this->environment);
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/*.yaml');
        if (!is_array($files) || [] === $files) {
            return [];
        }

        sort($files);

        $result = [];
        foreach ($files as $file) {
            $parsed = Yaml::parseFile($file);
            if (!is_array($parsed)) {
                continue;
            }

            if (isset($parsed['notifications']) && is_array($parsed['notifications'])) {
                foreach ($parsed['notifications'] as $config) {
                    if (!is_array($config)) {
                        continue;
                    }

                    $normalized = $this->normalizeConfig($config);
                    if (null !== $normalized) {
                        $result[] = $normalized;
                    }
                }

                continue;
            }

            $normalized = $this->normalizeConfig($parsed);
            if (null !== $normalized) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{
     *     code: string,
     *     type: string,
     *     date: int|array<string, scalar>,
     *     triggers: list<string>,
     *     delivery_types: list<string>,
     *     template: string,
     *     frequency: array{
     *         mode: string,
     *         interval_seconds: int
     *     }
     * }|null
     */
    private function normalizeConfig(array $config): ?array
    {
        $type = isset($config['type']) ? (string) $config['type'] : '';
        if ('' === trim($type)) {
            return null;
        }

        $template = isset($config['template']) ? (string) $config['template'] : '';
        if ('' === trim($template)) {
            return null;
        }

        $date = $config['date'] ?? null;
        if (!is_int($date) && !is_array($date)) {
            return null;
        }

        if (is_array($date)) {
            /** @var array<string, scalar> $date */
            $date = array_filter(
                $date,
                static fn (mixed $value): bool => is_scalar($value)
            );
            if ([] === $date) {
                return null;
            }
        }

        $triggerCodes = [];
        $triggers = $config['triggers'] ?? [];
        if (!is_array($triggers)) {
            return null;
        }

        foreach ($triggers as $trigger) {
            $triggerCode = (string) $trigger;
            if ('' === trim($triggerCode)) {
                continue;
            }

            $triggerCodes[] = $triggerCode;
        }

        if ([] === $triggerCodes) {
            return null;
        }

        $deliveryTypes = [];
        $rawDeliveryTypes = $config['delivery_types'] ?? [];
        if (!is_array($rawDeliveryTypes)) {
            return null;
        }

        foreach ($rawDeliveryTypes as $deliveryType) {
            $normalized = (string) $deliveryType;
            if ('' === trim($normalized)) {
                continue;
            }

            $deliveryTypes[] = $normalized;
        }

        if ([] === $deliveryTypes) {
            return null;
        }

        $frequency = $this->normalizeFrequency($config['frequency'] ?? null);
        if (null === $frequency) {
            return null;
        }

        $code = $this->resolveCode($config, $type, $template, $date, $triggerCodes, $deliveryTypes, $frequency);

        return [
            'code' => $code,
            'type' => $type,
            'date' => $date,
            'triggers' => array_values(array_unique($triggerCodes)),
            'delivery_types' => array_values(array_unique($deliveryTypes)),
            'template' => $template,
            'frequency' => $frequency,
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     interval_seconds: int
     * }|null
     */
    private function normalizeFrequency(mixed $raw): ?array
    {
        if (null === $raw) {
            return [
                'mode' => 'every_time',
                'interval_seconds' => 0,
            ];
        }

        if (is_string($raw)) {
            $raw = ['mode' => $raw];
        }

        if (!is_array($raw)) {
            return null;
        }

        $mode = mb_strtolower(trim((string) ($raw['mode'] ?? $raw['type'] ?? 'every_time')));
        if (!in_array($mode, ['once', 'interval', 'every_time'], true)) {
            return null;
        }

        if ('interval' !== $mode) {
            return [
                'mode' => $mode,
                'interval_seconds' => 0,
            ];
        }

        $intervalSeconds = (int) ($raw['interval_seconds'] ?? 0);
        if ($intervalSeconds <= 0) {
            return null;
        }

        return [
            'mode' => 'interval',
            'interval_seconds' => $intervalSeconds,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param int|array<string, scalar> $date
     * @param list<string> $triggerCodes
     * @param list<string> $deliveryTypes
     * @param array{mode: string, interval_seconds: int} $frequency
     */
    private function resolveCode(
        array $config,
        string $type,
        string $template,
        int|array $date,
        array $triggerCodes,
        array $deliveryTypes,
        array $frequency,
    ): string {
        $provided = mb_strtolower(trim((string) ($config['code'] ?? '')));
        if ('' !== $provided) {
            return preg_replace('/[^a-z0-9:_-]/', '_', $provided) ?? $provided;
        }

        $signature = [
            'type' => $type,
            'template' => $template,
            'date' => $date,
            'triggers' => $triggerCodes,
            'delivery_types' => $deliveryTypes,
            'frequency' => $frequency,
        ];

        return 'trigger_'.substr(sha1((string) json_encode($signature)), 0, 16);
    }
}
