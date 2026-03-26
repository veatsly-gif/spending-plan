<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fixtures\BaseUsersFixture;

final class SecurityAccessTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [BaseUsersFixture::class];
    }

    public function testDashboardRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testRegularUserCannotOpenAdminDashboard(): void
    {
        $this->loginAs('test');
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanOpenAdminDashboard(): void
    {
        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Control Center');
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/telegram"]')->count());
    }
}
