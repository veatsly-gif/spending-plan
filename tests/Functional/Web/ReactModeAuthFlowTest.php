<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class ReactModeAuthFlowTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [BaseUsersFixture::class];
    }

    protected function getFrontendModeForTest(): string
    {
        return 'react';
    }

    public function testLoginPageRendersReactRootInReactMode(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/app/login');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('#root')->count());
    }

    public function testAuthenticatedUserSeesReactDashboardPage(): void
    {
        $this->loginAs('test');
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/app/dashboard');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('#root')->count());
    }

    public function testLoginRedirectsToDashboardWhenAlreadyAuthenticated(): void
    {
        $this->loginAs('test');
        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/app/dashboard');
    }
}
