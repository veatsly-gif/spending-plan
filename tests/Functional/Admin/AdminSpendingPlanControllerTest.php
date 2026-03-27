<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\SpendingPlan;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\CurrentMonthSpendingPlanFixture;
use App\Tests\Functional\DatabaseWebTestCase;
use App\Service\SpendingPlanSuggestionCacheService;

final class AdminSpendingPlanControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testAdminCanSeeCurrentAndNextMonthTabs' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                CurrentMonthSpendingPlanFixture::class,
            ],
            'testAdminCanChangeSpendingPlanCurrencyOnEdit' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                CurrentMonthSpendingPlanFixture::class,
            ],
            default => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
            ],
        };
    }

    public function testAdminCanSeeCurrentAndNextMonthTabs(): void
    {
        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin/spending-plans');

        self::assertResponseIsSuccessful();

        $tabNodes = $crawler->filter('.sp-tab');
        self::assertGreaterThanOrEqual(2, $tabNodes->count());

        $nextMonth = (new \DateTimeImmutable('first day of next month'))->format('F Y');
        self::assertGreaterThanOrEqual(1, $crawler->filter('.sp-tab:contains("'.$nextMonth.'")')->count());
    }

    public function testAdminCanApproveSuggestionWithAjax(): void
    {
        $nextMonthStart = new \DateTimeImmutable('first day of next month');
        $monthKey = $nextMonthStart->format('Y-m');
        $suggestionCache = static::getContainer()->get(SpendingPlanSuggestionCacheService::class);
        $suggestionCache->storeSuggestions(
            $monthKey,
            $suggestionCache->buildMonthSuggestions($nextMonthStart)
        );

        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin/spending-plans');
        self::assertResponseIsSuccessful();

        $suggestion = $crawler->filter('[data-suggestion-id]')->first();
        self::assertGreaterThanOrEqual(1, $crawler->filter('[data-suggestion-id]')->count());
        $suggestionId = (string) $suggestion->attr('data-suggestion-id');

        $html = (string) $this->client->getResponse()->getContent();
        $matched = preg_match('/csrfToken:\s*"([^"]+)"/', $html, $matches);
        self::assertSame(1, $matched);
        $token = (string) ($matches[1] ?? '');

        $this->client->request(
            'POST',
            sprintf('/admin/spending-plans/suggestions/%s/%s/approve', $monthKey, $suggestionId),
            [
                '_token' => $token,
                'limitAmount' => '333.33',
                'currencyCode' => 'EUR',
                'weight' => '9',
                'note' => 'Custom April scenario',
            ],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertTrue((bool) ($data['success'] ?? false));

        $this->entityManager->clear();
        $plan = $this->entityManager
            ->getRepository(SpendingPlan::class)
            ->findOneBy(['limitAmount' => '333.33']);
        self::assertInstanceOf(SpendingPlan::class, $plan);
        self::assertSame('EUR', $plan->getCurrency()?->getCode());
        self::assertSame(9, $plan->getWeight());
        self::assertSame('Custom April scenario', $plan->getNote());
    }

    public function testAdminCanCreateSpendingPlan(): void
    {
        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin/spending-plans/new');

        self::assertResponseIsSuccessful();
        $currencyValue = (string) $crawler
            ->filter('select[name="admin_spending_plan[currency]"] option')
            ->first()
            ->attr('value');

        $form = $crawler->selectButton('Create')->form([
            'admin_spending_plan[name]' => 'Nastya birthday',
            'admin_spending_plan[planType]' => SpendingPlan::PLAN_TYPE_CUSTOM,
            'admin_spending_plan[dateFrom]' => '2026-04-04',
            'admin_spending_plan[dateTo]' => '2026-04-04',
            'admin_spending_plan[limitAmount]' => '1000',
            'admin_spending_plan[currency]' => $currencyValue,
            'admin_spending_plan[weight]' => '10',
            'admin_spending_plan[isSystem]' => false,
            'admin_spending_plan[note]' => 'Birthday present',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $spendingPlan = $this->entityManager
            ->getRepository(SpendingPlan::class)
            ->findOneBy(['name' => 'Nastya birthday']);
        self::assertInstanceOf(SpendingPlan::class, $spendingPlan);
        self::assertSame('1000.00', $spendingPlan->getLimitAmount());
        self::assertSame(10, $spendingPlan->getWeight());
    }

    public function testAdminCanChangeSpendingPlanCurrencyOnEdit(): void
    {
        $this->loginAs('admin');

        $plan = $this->entityManager
            ->getRepository(SpendingPlan::class)
            ->findOneBy(['name' => 'March base plan']);
        self::assertInstanceOf(SpendingPlan::class, $plan);
        self::assertSame('GEL', $plan->getCurrency()?->getCode());

        $crawler = $this->client->request(
            'GET',
            sprintf('/admin/spending-plans/%d/edit', $plan->getId())
        );
        self::assertResponseIsSuccessful();

        $eurOption = $crawler->filterXPath(
            '//select[@name="admin_spending_plan[currency]"]/option[contains(normalize-space(.), "EUR")]'
        )->first();
        self::assertCount(1, $eurOption);
        $eurValue = (string) $eurOption->attr('value');

        $form = $crawler->selectButton('Save')->form([
            'admin_spending_plan[name]' => (string) $plan->getName(),
            'admin_spending_plan[planType]' => (string) $plan->getPlanType(),
            'admin_spending_plan[dateFrom]' => $plan->getDateFrom()->format('Y-m-d'),
            'admin_spending_plan[dateTo]' => $plan->getDateTo()->format('Y-m-d'),
            'admin_spending_plan[limitAmount]' => (string) $plan->getLimitAmount(),
            'admin_spending_plan[currency]' => $eurValue,
            'admin_spending_plan[weight]' => (string) $plan->getWeight(),
            'admin_spending_plan[isSystem]' => $plan->isSystem(),
            'admin_spending_plan[note]' => (string) ($plan->getNote() ?? ''),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(SpendingPlan::class)->find($plan->getId());
        self::assertInstanceOf(SpendingPlan::class, $updated);
        self::assertSame('EUR', $updated->getCurrency()?->getCode());
    }
}
