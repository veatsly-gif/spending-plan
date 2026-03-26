<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Functional\DatabaseWebTestCase;

final class HealthControllerTest extends DatabaseWebTestCase
{
    public function testHealthEndpointReturnsOkPayload(): void
    {
        $this->client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(
            'application/json',
            (string) $this->client->getResponse()->headers->get('content-type')
        );

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'ok', 'service' => 'spending-plan'], $payload);
    }
}
