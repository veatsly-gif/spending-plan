<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;

final class NotificationActionService
{
    public const ACTION_DONE = 'done';
    public const ACTION_REMIND_LATER = 'remind_later';
    public const TEMPLATE_DECLARATION_SEND_TAX_SERVICE = 'declaration_send_tax_service';

    private const CALLBACK_PREFIX = 'nf';

    /**
     * @var array<string, bool>
     */
    private const ACTIONABLE_TEMPLATES = [
        self::TEMPLATE_DECLARATION_SEND_TAX_SERVICE => true,
    ];

    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    public function shouldNotify(
        int $userId,
        string $templateCode,
        string $monthKey,
        \DateTimeImmutable $now,
    ): bool {
        $templateCode = $this->normalizeTemplateCode($templateCode);
        if (!$this->isValidMonthKey($monthKey) || !$this->isActionableTemplate($templateCode)) {
            return true;
        }

        $state = $this->readState($userId, $templateCode, $monthKey);
        if ($state['done']) {
            return false;
        }

        $snoozeUntil = $state['snoozeUntil'];
        if (null === $snoozeUntil) {
            return true;
        }

        return $now->format('Y-m-d') >= $snoozeUntil;
    }

    public function applyAction(
        User $user,
        string $templateCode,
        string $monthKey,
        string $actionCode,
        \DateTimeImmutable $now,
    ): bool {
        return $this->applyActionByUserId((int) $user->getId(), $templateCode, $monthKey, $actionCode, $now);
    }

    public function applyActionByUserId(
        int $userId,
        string $templateCode,
        string $monthKey,
        string $actionCode,
        \DateTimeImmutable $now,
    ): bool {
        $templateCode = $this->normalizeTemplateCode($templateCode);
        $actionCode = mb_strtolower(trim($actionCode));
        if (!$this->isValidMonthKey($monthKey) || !$this->isActionableTemplate($templateCode)) {
            return false;
        }

        $state = $this->readState($userId, $templateCode, $monthKey);
        if (self::ACTION_DONE === $actionCode) {
            $state['done'] = true;
            $state['snoozeUntil'] = null;
            $this->writeState($userId, $templateCode, $monthKey, $state, $now);

            return true;
        }

        if (self::ACTION_REMIND_LATER !== $actionCode) {
            return false;
        }

        $state['done'] = false;
        $state['snoozeUntil'] = $now->modify('+1 day')->format('Y-m-d');
        $this->writeState($userId, $templateCode, $monthKey, $state, $now);

        return true;
    }

    public function buildTelegramCallbackData(
        string $templateCode,
        string $monthKey,
        string $actionCode,
    ): ?string {
        $templateCode = $this->normalizeTemplateCode($templateCode);
        $actionCode = mb_strtolower(trim($actionCode));

        if (!$this->isValidMonthKey($monthKey) || !$this->isActionableTemplate($templateCode)) {
            return null;
        }

        if (!in_array($actionCode, [self::ACTION_DONE, self::ACTION_REMIND_LATER], true)) {
            return null;
        }

        $callbackData = sprintf('%s|%s|%s|%s', self::CALLBACK_PREFIX, $templateCode, $monthKey, $actionCode);
        if (strlen($callbackData) > 64) {
            return null;
        }

        return $callbackData;
    }

    /**
     * @return array{
     *     templateCode: string,
     *     monthKey: string,
     *     actionCode: string
     * }|null
     */
    public function parseTelegramCallbackData(string $callbackData): ?array
    {
        $parts = explode('|', trim($callbackData));
        if (4 !== count($parts) || self::CALLBACK_PREFIX !== ($parts[0] ?? '')) {
            return null;
        }

        $templateCode = $this->normalizeTemplateCode((string) ($parts[1] ?? ''));
        $monthKey = (string) ($parts[2] ?? '');
        $actionCode = mb_strtolower(trim((string) ($parts[3] ?? '')));

        if (!$this->isValidMonthKey($monthKey) || !$this->isActionableTemplate($templateCode)) {
            return null;
        }

        if (!in_array($actionCode, [self::ACTION_DONE, self::ACTION_REMIND_LATER], true)) {
            return null;
        }

        return [
            'templateCode' => $templateCode,
            'monthKey' => $monthKey,
            'actionCode' => $actionCode,
        ];
    }

    private function isActionableTemplate(string $templateCode): bool
    {
        return isset(self::ACTIONABLE_TEMPLATES[$templateCode]);
    }

    private function isValidMonthKey(string $monthKey): bool
    {
        return 1 === preg_match('/^\d{4}-\d{2}$/', $monthKey);
    }

    private function normalizeTemplateCode(string $templateCode): string
    {
        return mb_strtolower(trim($templateCode));
    }

    /**
     * @return array{
     *     done: bool,
     *     snoozeUntil: string|null
     * }
     */
    private function readState(
        int $userId,
        string $templateCode,
        string $monthKey,
    ): array {
        $decoded = $this->redisStore->getJsonByDataKey(
            RedisDataKey::NOTIFICATION_ACTION_STATE,
            $this->stateContext($userId, $templateCode, $monthKey)
        );
        if (null === $decoded) {
            return ['done' => false, 'snoozeUntil' => null];
        }

        $snoozeUntil = isset($decoded['snoozeUntil']) ? (string) $decoded['snoozeUntil'] : null;
        if (null !== $snoozeUntil && 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $snoozeUntil)) {
            $snoozeUntil = null;
        }

        return [
            'done' => true === ($decoded['done'] ?? false),
            'snoozeUntil' => $snoozeUntil,
        ];
    }

    /**
     * @param array{
     *     done: bool,
     *     snoozeUntil: string|null
     * } $state
     */
    private function writeState(
        int $userId,
        string $templateCode,
        string $monthKey,
        array $state,
        \DateTimeImmutable $now,
    ): void {
        $ttl = $this->ttlUntilStateExpiry($monthKey, $now);

        $this->redisStore->setJsonByDataKey(
            RedisDataKey::NOTIFICATION_ACTION_STATE,
            $this->stateContext($userId, $templateCode, $monthKey),
            $state,
            $ttl
        );
    }

    private function ttlUntilStateExpiry(string $monthKey, \DateTimeImmutable $now): int
    {
        $monthStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthKey.'-01 00:00:00');
        if (!$monthStart instanceof \DateTimeImmutable) {
            return 86400;
        }

        $expiresAt = $monthStart
            ->modify('last day of this month')
            ->setTime(23, 59, 59)
            ->modify('+40 days');

        return max(60, $expiresAt->getTimestamp() - $now->getTimestamp());
    }

    /**
     * @return array{userId: string, templateCode: string, monthKey: string}
     */
    private function stateContext(int $userId, string $templateCode, string $monthKey): array
    {
        return [
            'userId' => (string) $userId,
            'templateCode' => $templateCode,
            'monthKey' => $monthKey,
        ];
    }
}
