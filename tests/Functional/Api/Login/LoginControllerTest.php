<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Login;

use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class LoginControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [BaseUsersFixture::class];
    }

    public function testTokenLoginSuccessAndStubPasses(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'username' => 'admin',
            'password' => 'admin',
        ]);

        self::assertResponseIsSuccessful();
        $loginPayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($loginPayload['success'] ?? false));
        self::assertIsString($loginPayload['token'] ?? null);
        self::assertNotSame('', (string) ($loginPayload['token'] ?? ''));

        $this->client->request('GET', '/api/login/stub', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginPayload['token'],
        ]);
        self::assertResponseIsSuccessful();

        $stubPayload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($stubPayload['success'] ?? false));
        self::assertSame('admin', $stubPayload['user']['identifier'] ?? null);
    }

    public function testTokenLoginFailsWithInvalidPassword(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'username' => 'admin',
            'password' => 'wrong',
        ]);

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse((bool) ($payload['success'] ?? true));
    }

    public function testStubRequiresApiToken(): void
    {
        $this->client->request('GET', '/api/login/stub');

        self::assertResponseStatusCodeSame(401);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse((bool) ($payload['success'] ?? true));
    }
}
