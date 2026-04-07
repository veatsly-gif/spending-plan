<?php

declare(strict_types=1);

namespace App\Util;

final readonly class AppDateTimeFormatter
{
    private \DateTimeZone $timezone;

    public function __construct(string $timezoneName)
    {
        try {
            $this->timezone = new \DateTimeZone($timezoneName);
        } catch (\DateInvalidTimeZoneException $exception) {
            throw new \InvalidArgumentException(
                sprintf('Invalid APP_DISPLAY_TIMEZONE value "%s".', $timezoneName),
                previous: $exception
            );
        }
    }

    public function format(\DateTimeInterface $dateTime, string $format): string
    {
        return \DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone($this->timezone)
            ->format($format);
    }
}
