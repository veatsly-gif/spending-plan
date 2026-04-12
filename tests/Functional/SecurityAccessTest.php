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
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/spending-plans');
        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('h1')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/admin/telegram"]')->count());
    }

    public function testRegularUserCannotOpenAdminTechTasksPage(): void
    {
        $this->loginAs('test');
        $this->client->request('GET', '/admin/tech-tasks');

        self::assertResponseStatusCodeSame(403);
    }
}
