<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\AppDateTimeFormatter;
use PHPUnit\Framework\TestCase;

final class AppDateTimeFormatterTest extends TestCase
{
    public function testFormatsDateTimeInConfiguredTimezone(): void
    {
        $formatter = new AppDateTimeFormatter('Asia/Tbilisi');

        $result = $formatter->format(
            new \DateTimeImmutable('2026-04-07 10:15:00', new \DateTimeZone('UTC')),
            'Y-m-d H:i'
        );

        self::assertSame('2026-04-07 14:15', $result);
    }

    public function testThrowsForInvalidTimezone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid APP_DISPLAY_TIMEZONE value');

        new AppDateTimeFormatter('Invalid/Timezone');
    }
}
