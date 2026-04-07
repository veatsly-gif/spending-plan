<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserMetadataTest extends TestCase
{
    public function testConstructorInitializesDefaultValues(): void
    {
        $metadata = new UserMetadata();

        self::assertNull($metadata->getId());
        self::assertSame([], $metadata->getPreferences());
        self::assertInstanceOf(\DateTimeImmutable::class, $metadata->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $metadata->getUpdatedAt());
    }

    public function testSetUser(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $result = $metadata->setUser($user);

        self::assertSame($user, $metadata->getUser());
        self::assertSame($metadata, $result);
    }

    public function testSetPreferences(): void
    {
        $metadata = new UserMetadata();
        $preferences = [
            'language' => 'en',
            'theme' => 'dark',
        ];

        $oldUpdatedAt = $metadata->getUpdatedAt();
        sleep(1); // Ensure time difference
        $result = $metadata->setPreferences($preferences);

        self::assertSame($preferences, $metadata->getPreferences());
        self::assertSame($metadata, $result);
        self::assertGreaterThan($oldUpdatedAt, $metadata->getUpdatedAt());
    }

    public function testSetPreference(): void
    {
        $metadata = new UserMetadata();

        $oldUpdatedAt = $metadata->getUpdatedAt();
        sleep(1);
        $result = $metadata->setPreference('language', 'ru');

        self::assertSame('ru', $metadata->getPreference('language'));
        self::assertSame($metadata, $result);
        self::assertGreaterThan($oldUpdatedAt, $metadata->getUpdatedAt());
    }

    public function testSetPreferenceAddsToExistingPreferences(): void
    {
        $metadata = new UserMetadata();
        $metadata->setPreference('language', 'en');
        $metadata->setPreference('theme', 'dark');

        $preferences = $metadata->getPreferences();
        self::assertCount(2, $preferences);
        self::assertSame('en', $preferences['language']);
        self::assertSame('dark', $preferences['theme']);
    }

    #[DataProvider('providePreferenceDefaults')]
    public function testGetPreferenceWithDefaults(string $key, mixed $expectedDefault): void
    {
        $metadata = new UserMetadata();

        // When key doesn't exist, should return default (null by default)
        self::assertEquals($expectedDefault, $metadata->getPreference($key, $expectedDefault));
    }

    public static function providePreferenceDefaults(): array
    {
        return [
            'non-existent string key' => ['nonexistent', null],
            'non-existent with default' => ['nonexistent', 'default_value'],
            'numeric default' => ['count', 0],
            'array default' => ['items', []],
        ];
    }

    public function testSetPreferenceOverwritesExistingValue(): void
    {
        $metadata = new UserMetadata();
        $metadata->setPreference('language', 'en');
        self::assertSame('en', $metadata->getPreference('language'));

        $metadata->setPreference('language', 'ru');
        self::assertSame('ru', $metadata->getPreference('language'));
    }

    public function testGetPreferenceReturnsStoredValueEvenWhenNull(): void
    {
        $metadata = new UserMetadata();
        $metadata->setPreference('language', null);

        // When key exists with null value, should return null (not the default)
        self::assertNull($metadata->getPreference('language', 'fallback'));

        // To get the fallback, the key must not exist
        self::assertSame('fallback', $metadata->getPreference('nonexistent', 'fallback'));
    }

    public function testFluentInterfaceForChaining(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata
            ->setUser($user)
            ->setPreference('language', 'en')
            ->setPreference('theme', 'light');

        self::assertSame($user, $metadata->getUser());
        self::assertSame('en', $metadata->getPreference('language'));
        self::assertSame('light', $metadata->getPreference('theme'));
    }

    public function testSetPreferencesWithComplexData(): void
    {
        $metadata = new UserMetadata();
        $complexPreferences = [
            'language' => 'en',
            'theme' => 'dark',
            'notifications' => true,
            'timezone' => 'UTC+3',
        ];

        $metadata->setPreferences($complexPreferences);

        self::assertSame($complexPreferences, $metadata->getPreferences());
        self::assertSame('en', $metadata->getPreference('language'));
        self::assertSame('dark', $metadata->getPreference('theme'));
        self::assertTrue($metadata->getPreference('notifications'));
        self::assertSame('UTC+3', $metadata->getPreference('timezone'));
    }
}
