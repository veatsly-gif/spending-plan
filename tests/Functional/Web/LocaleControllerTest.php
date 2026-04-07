<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Repository\UserMetadataRepository;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class LocaleControllerTest extends DatabaseWebTestCase
{
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            default => [BaseCurrenciesFixture::class, BaseUsersFixture::class],
        };
    }

    public function testSetLocalePersistsForAuthenticatedUser(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/locale/ru');

        self::assertResponseRedirects();

        // Verify persistence in database
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('ru', $metadata->getPreference('language'));
    }

    public function testSetLocaleUpdatesExistingPreferences(): void
    {
        $this->loginAs('test');

        // Create user metadata with existing preferences
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->findOneBy(['username' => 'test']);

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'dark']);

        $this->entityManager->persist($metadata);
        $this->entityManager->flush();

        // Change language
        $this->client->request('GET', '/locale/ru');

        self::assertResponseRedirects();

        // Verify both language changed and theme preserved
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        $updatedMetadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $updatedMetadata);
        self::assertSame('ru', $updatedMetadata->getPreference('language'));
        self::assertSame('dark', $updatedMetadata->getPreference('theme')); // Should be preserved
    }

    public function testSetLocaleNormalizesToLowercase(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/locale/RU');

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('ru', $metadata->getPreference('language'));
    }

    public function testSetLocaleDefaultsToEnForInvalidLocale(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/locale/fr');

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('en', $metadata->getPreference('language'));
    }

    public function testSetLocaleSetsCookie(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/locale/ru');

        $cookie = $this->client->getCookieJar()->get('_locale');
        self::assertNotNull($cookie);
        self::assertSame('ru', $cookie->getValue());
    }
}
