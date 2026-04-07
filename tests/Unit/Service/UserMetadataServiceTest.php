<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Repository\UserMetadataRepository;
use App\Service\UserMetadataService;
use PHPUnit\Framework\TestCase;

final class UserMetadataServiceTest extends TestCase
{
    private UserMetadataRepository $repository;
    private UserMetadataService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserMetadataRepository::class);
        $this->service = new UserMetadataService($this->repository);
    }

    public function testGetUserMetadataReturnsExistingMetadata(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $existingMetadata = new UserMetadata();
        $existingMetadata->setUser($user);
        $existingMetadata->setPreferences(['language' => 'ru', 'theme' => 'dark']);

        $this->repository
            ->expects(self::once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($existingMetadata);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $result = $this->service->getUserMetadata($user);

        self::assertSame($existingMetadata, $result);
        self::assertSame('ru', $result->getPreference('language'));
        self::assertSame('dark', $result->getPreference('theme'));
    }

    public function testGetUserMetadataCreatesNewMetadataWhenNotExists(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $this->repository
            ->expects(self::once())
            ->method('findByUser')
            ->with($user)
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(function (UserMetadata $metadata) use ($user): bool {
                    return $metadata->getUser() === $user
                        && $metadata->getPreference('language') === 'en'
                        && $metadata->getPreference('theme') === 'light';
                }),
                true
            );

        $result = $this->service->getUserMetadata($user);

        self::assertInstanceOf(UserMetadata::class, $result);
        self::assertSame($user, $result->getUser());
        self::assertSame('en', $result->getPreference('language'));
        self::assertSame('light', $result->getPreference('theme'));
    }

    public function testGetPreferencesReturnsCorrectValues(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'ru', 'theme' => 'dark']);

        $this->repository
            ->method('findByUser')
            ->willReturn($metadata);

        $result = $this->service->getPreferences($user);

        self::assertSame(['language' => 'ru', 'theme' => 'dark'], $result);
    }

    public function testGetPreferencesReturnsDefaultsWhenMetadataCreated(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $this->repository
            ->method('findByUser')
            ->willReturnCallback(function () use ($user) {
                $metadata = new UserMetadata();
                $metadata->setUser($user);
                $metadata->setPreferences(['language' => 'en', 'theme' => 'light']);
                return $metadata;
            });

        $this->repository
            ->method('save');

        $result = $this->service->getPreferences($user);

        self::assertSame(['language' => 'en', 'theme' => 'light'], $result);
    }

    public function testUpdatePreferenceUpdatesExistingMetadata(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'light']);

        $this->repository
            ->method('findByUser')
            ->willReturn($metadata);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(function (UserMetadata $updatedMetadata): bool {
                    return $updatedMetadata->getPreference('language') === 'ru'
                        && $updatedMetadata->getPreference('theme') === 'light';
                }),
                true
            );

        $result = $this->service->updatePreference($user, 'language', 'ru');

        self::assertSame($metadata, $result);
        self::assertSame('ru', $result->getPreference('language'));
    }

    public function testUpdatePreferenceCreatesMetadataWhenNotExists(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $this->repository
            ->method('findByUser')
            ->willReturn(null);

        // save will be called twice: once in getUserMetadata (creating defaults) and once in updatePreference
        $callCount = 0;
        $this->repository
            ->expects(self::exactly(2))
            ->method('save');

        $result = $this->service->updatePreference($user, 'theme', 'dark');

        self::assertSame('dark', $result->getPreference('theme'));
    }

    public function testUpdatePreferencesMergesWithExistingPreferences(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'light']);

        $this->repository
            ->method('findByUser')
            ->willReturn($metadata);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(function (UserMetadata $updatedMetadata): bool {
                    $prefs = $updatedMetadata->getPreferences();
                    return $prefs['language'] === 'ru'
                        && $prefs['theme'] === 'dark'
                        && count($prefs) === 2;
                }),
                true
            );

        $result = $this->service->updatePreferences($user, [
            'language' => 'ru',
            'theme' => 'dark',
        ]);

        self::assertSame('ru', $result->getPreference('language'));
        self::assertSame('dark', $result->getPreference('theme'));
    }

    public function testUpdatePreservesUnchangedPreferences(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences([
            'language' => 'en',
            'theme' => 'light',
        ]);

        $this->repository
            ->method('findByUser')
            ->willReturn($metadata);

        $this->repository
            ->method('save')
            ->with(
                self::callback(function (UserMetadata $updatedMetadata): bool {
                    $prefs = $updatedMetadata->getPreferences();
                    // Only language should change, theme stays the same
                    return $prefs['language'] === 'ru'
                        && $prefs['theme'] === 'light';
                })
            );

        $this->service->updatePreferences($user, ['language' => 'ru']);
    }

    public function testGetPreferenceDelegatesToMetadata(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password123');

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'dark']);

        $this->repository
            ->method('findByUser')
            ->willReturn($metadata);

        self::assertSame('en', $this->service->getPreference($user, 'language'));
        self::assertSame('dark', $this->service->getPreference($user, 'theme'));
        self::assertNull($this->service->getPreference($user, 'nonexistent'));
        self::assertSame('default', $this->service->getPreference($user, 'nonexistent', 'default'));
    }
}
