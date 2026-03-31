<?php

declare(strict_types=1);

namespace App\Service\Income;

use App\DTO\Income\IncomeLiveRatesDto;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IncomeRateService
{
    private const NBG_ENDPOINT = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/';
    private const COINGECKO_ENDPOINT = 'https://api.coingecko.com/api/v3/simple/price';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RedisStore $redisStore,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function refreshLiveRates(\DateTimeImmutable $now): ?IncomeLiveRatesDto
    {
        $eurGel = $this->resolveOfficialRate('EUR', $now, 7);
        $usdtGel = $this->fetchUsdtGelFromOpenSource();
        if (null === $usdtGel) {
            $usdtGel = $this->resolveOfficialRate('USD', $now, 7);
        }

        if (null === $eurGel || null === $usdtGel) {
            $this->logger->warning('Unable to refresh live income rates.', [
                'eur_gel' => $eurGel,
                'usdt_gel' => $usdtGel,
            ]);

            return null;
        }

        $dto = new IncomeLiveRatesDto(
            number_format($eurGel, 6, '.', ''),
            number_format($usdtGel, 6, '.', ''),
            $now
        );
        $this->redisStore->setJsonByDataKey(
            RedisDataKey::INCOME_RATES_LIVE,
            [],
            $dto->toArray()
        );

        return $dto;
    }

    public function getLiveRates(): ?IncomeLiveRatesDto
    {
        $payload = $this->redisStore->getJsonByDataKey(RedisDataKey::INCOME_RATES_LIVE, []);
        if (null === $payload) {
            return null;
        }

        return IncomeLiveRatesDto::fromArray($payload);
    }

    public function convertAmountToGel(string $amount, string $currencyCode): ?string
    {
        if (!is_numeric($amount)) {
            return null;
        }

        $normalizedCode = strtoupper(trim($currencyCode));
        if ('GEL' === $normalizedCode) {
            return number_format((float) $amount, 2, '.', '');
        }

        $rates = $this->getLiveRates();
        if (null === $rates) {
            $rates = $this->refreshLiveRates(new \DateTimeImmutable());
        }

        if (null === $rates) {
            return null;
        }

        $rate = match ($normalizedCode) {
            'EUR' => (float) $rates->eurGel,
            'USDT' => (float) $rates->usdtGel,
            default => null,
        };
        if (null === $rate) {
            return null;
        }

        return number_format(((float) $amount) * $rate, 2, '.', '');
    }

    public function getLiveGelRateForCurrency(string $currencyCode): ?string
    {
        $normalizedCode = strtoupper(trim($currencyCode));
        if ('GEL' === $normalizedCode) {
            return '1.0000';
        }

        $rates = $this->getLiveRates();
        if (null === $rates) {
            $rates = $this->refreshLiveRates(new \DateTimeImmutable());
        }
        if (null === $rates) {
            return null;
        }

        $rate = match ($normalizedCode) {
            'EUR' => (float) $rates->eurGel,
            'USDT' => (float) $rates->usdtGel,
            default => null,
        };
        if (null === $rate) {
            return null;
        }

        return number_format($rate, 4, '.', '');
    }

    /**
     * @return array<string, string>|null
     */
    public function getLiveGelRates(): ?array
    {
        $rates = $this->getLiveRates();
        if (null === $rates) {
            $rates = $this->refreshLiveRates(new \DateTimeImmutable());
        }
        if (null === $rates) {
            return null;
        }

        return [
            'GEL' => '1.0000',
            'EUR' => number_format((float) $rates->eurGel, 4, '.', ''),
            'USDT' => number_format((float) $rates->usdtGel, 4, '.', ''),
        ];
    }

    public function getOfficialGelRateForDate(string $currencyCode, \DateTimeImmutable $date): ?string
    {
        $normalizedCode = strtoupper(trim($currencyCode));
        if ('GEL' === $normalizedCode) {
            return '1.000000';
        }

        $sourceCode = 'USDT' === $normalizedCode ? 'USD' : $normalizedCode;
        $rate = $this->resolveOfficialRate($sourceCode, $date, 7);
        if (null === $rate) {
            return null;
        }

        return number_format($rate, 6, '.', '');
    }

    /**
     * @param list<string> $currencyCodes
     * @return array<string, string>
     */
    public function getOfficialGelRatesForDate(\DateTimeImmutable $date, array $currencyCodes): array
    {
        $result = [];
        $normalizedCodes = [];
        foreach ($currencyCodes as $currencyCode) {
            $code = strtoupper(trim($currencyCode));
            if ('' === $code) {
                continue;
            }

            $normalizedCodes[$code] = true;
            if ('GEL' === $code) {
                $result[$code] = '1.000000';
            }
        }

        if ([] === $normalizedCodes) {
            return $result;
        }

        $apiCodeMap = [];
        foreach (array_keys($normalizedCodes) as $code) {
            if ('GEL' === $code) {
                continue;
            }

            $apiCodeMap[$code] = 'USDT' === $code ? 'USD' : $code;
        }

        $rates = $this->resolveOfficialRatesForCodes(
            array_values(array_unique(array_values($apiCodeMap))),
            $date,
            7
        );

        foreach ($apiCodeMap as $originalCode => $apiCode) {
            if (!isset($rates[$apiCode])) {
                continue;
            }

            $result[$originalCode] = number_format((float) $rates[$apiCode], 6, '.', '');
        }

        return $result;
    }

    private function fetchUsdtGelFromOpenSource(): ?float
    {
        try {
            $response = $this->httpClient->request('GET', self::COINGECKO_ENDPOINT, [
                'query' => [
                    'ids' => 'tether',
                    'vs_currencies' => 'gel',
                ],
                'timeout' => 8,
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Unable to fetch USDT/GEL rate from CoinGecko.', [
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $tether = $payload['tether'] ?? null;
        if (!is_array($tether)) {
            return null;
        }

        return $this->parseNumericValue($tether['gel'] ?? null);
    }

    private function resolveOfficialRate(
        string $currencyCode,
        \DateTimeImmutable $fromDate,
        int $maxDaysBack,
    ): ?float {
        $current = $fromDate;
        for ($attempt = 0; $attempt <= $maxDaysBack; $attempt++) {
            $rate = $this->fetchOfficialRateForDay($currencyCode, $current);
            if (null !== $rate) {
                return $rate;
            }

            $current = $current->modify('-1 day');
        }

        return null;
    }

    /**
     * @param list<string> $currencyCodes
     * @return array<string, float>
     */
    private function resolveOfficialRatesForCodes(
        array $currencyCodes,
        \DateTimeImmutable $fromDate,
        int $maxDaysBack,
    ): array {
        $pending = [];
        foreach ($currencyCodes as $currencyCode) {
            $pending[strtoupper(trim($currencyCode))] = true;
        }

        $result = [];
        $current = $fromDate;
        for ($attempt = 0; $attempt <= $maxDaysBack; $attempt++) {
            if ([] === $pending) {
                break;
            }

            $dayRates = $this->fetchOfficialRatesForDay(array_keys($pending), $current);
            foreach ($dayRates as $code => $rate) {
                $result[$code] = $rate;
                unset($pending[$code]);
            }

            $current = $current->modify('-1 day');
        }

        return $result;
    }

    private function fetchOfficialRateForDay(
        string $currencyCode,
        \DateTimeImmutable $date,
    ): ?float {
        $query = [
            'date' => $date->format('Y-m-d'),
        ];

        try {
            $response = $this->httpClient->request('GET', self::NBG_ENDPOINT, [
                'query' => $query,
                'timeout' => 8,
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Unable to fetch official NBG rate.', [
                'currency' => $currencyCode,
                'date' => $date->format('Y-m-d'),
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }

        return $this->extractNbgRate($payload, $currencyCode);
    }

    /**
     * @param list<string> $currencyCodes
     * @return array<string, float>
     */
    private function fetchOfficialRatesForDay(
        array $currencyCodes,
        \DateTimeImmutable $date,
    ): array {
        $query = [
            'date' => $date->format('Y-m-d'),
        ];

        try {
            $response = $this->httpClient->request('GET', self::NBG_ENDPOINT, [
                'query' => $query,
                'timeout' => 8,
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Unable to fetch official NBG rates.', [
                'date' => $date->format('Y-m-d'),
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }

        return $this->extractNbgRates($payload, $currencyCodes);
    }

    /**
     * @param mixed $payload
     */
    private function extractNbgRate(mixed $payload, string $currencyCode): ?float
    {
        if (!is_array($payload)) {
            return null;
        }

        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $currencies = $row['currencies'] ?? null;
            if (!is_array($currencies)) {
                continue;
            }

            foreach ($currencies as $currencyRow) {
                if (!is_array($currencyRow)) {
                    continue;
                }

                $code = strtoupper((string) ($currencyRow['code'] ?? ''));
                if (strtoupper($currencyCode) !== $code) {
                    continue;
                }

                $rate = $this->parseNumericValue($currencyRow['rate'] ?? null);
                $quantity = $this->parseNumericValue($currencyRow['quantity'] ?? 1);
                if (null === $rate || null === $quantity || $quantity <= 0) {
                    continue;
                }

                return $rate / $quantity;
            }
        }

        return null;
    }

    /**
     * @param mixed $payload
     * @param list<string> $currencyCodes
     * @return array<string, float>
     */
    private function extractNbgRates(mixed $payload, array $currencyCodes): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $required = [];
        foreach ($currencyCodes as $currencyCode) {
            $required[strtoupper(trim($currencyCode))] = true;
        }

        $result = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $currencies = $row['currencies'] ?? null;
            if (!is_array($currencies)) {
                continue;
            }

            foreach ($currencies as $currencyRow) {
                if (!is_array($currencyRow)) {
                    continue;
                }

                $code = strtoupper((string) ($currencyRow['code'] ?? ''));
                if (!isset($required[$code])) {
                    continue;
                }

                $rate = $this->parseNumericValue($currencyRow['rate'] ?? null);
                $quantity = $this->parseNumericValue($currencyRow['quantity'] ?? 1);
                if (null === $rate || null === $quantity || $quantity <= 0) {
                    continue;
                }

                $result[$code] = $rate / $quantity;
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    private function parseNumericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(' ', '', trim($value));
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
