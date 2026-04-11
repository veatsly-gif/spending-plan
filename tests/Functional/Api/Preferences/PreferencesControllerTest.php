<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Preferences;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class PreferencesControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [BaseUsersFixture::class];
    }

    public function testPreferencesRequireApiToken(): void
    {
        $this->client->request('GET', '/api/preferences');
        self::assertResponseStatusCodeSame(401);

        $this->client->request(
            'POST',
            '/api/preferences',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['language' => 'ru'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedUserCanPersistPreferences(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);

        $this->client->request(
            'POST',
            '/api/preferences',
            [],
            [],
            array_merge($this->bearerHeaders($token), [
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'language' => 'ru',
                'theme' => 'dark',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($response['success'] ?? false));
        self::assertSame('ru', $response['preferences']['language'] ?? null);
        self::assertSame('dark', $response['preferences']['theme'] ?? null);

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => BaseUsersFixture::TEST_USERNAME]);
        self::assertInstanceOf(User::class, $user);

        $metadata = $this->entityManager->getRepository(UserMetadata::class)->findOneBy(['user' => $user]);
        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('ru', $metadata->getPreference('language'));
        self::assertSame('dark', $metadata->getPreference('theme'));
    }

    public function testGetPreferencesReturnsPersistedValues(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);

        $this->client->request(
            'POST',
            '/api/preferences',
            [],
            [],
            array_merge($this->bearerHeaders($token), [
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'language' => 'ru',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/preferences', [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();

        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($response['success'] ?? false));
        self::assertSame('ru', $response['preferences']['language'] ?? null);
    }

    private function loginViaApi(string $username, string $password): string
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'username' => $username,
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = isset($payload['token']) ? (string) $payload['token'] : '';
        self::assertNotSame('', $token);

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }
}
