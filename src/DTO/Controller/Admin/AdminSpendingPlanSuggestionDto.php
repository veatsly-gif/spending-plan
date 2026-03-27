<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminSpendingPlanSuggestionDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $planType,
        public string $dateFrom,
        public string $dateTo,
        public string $limitAmount,
        public string $currency,
        public int $weight,
        public ?string $note,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     planType: string,
     *     dateFrom: string,
     *     dateTo: string,
     *     limitAmount: string,
     *     currency: string,
     *     weight: int,
     *     note: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'planType' => $this->planType,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'limitAmount' => $this->limitAmount,
            'currency' => $this->currency,
            'weight' => $this->weight,
            'note' => $this->note,
        ];
    }

    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     planType: string,
     *     dateFrom: string,
     *     dateTo: string,
     *     limitAmount: string,
     *     currency: string,
     *     weight: int,
     *     note: ?string
     * } $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['id'],
            $payload['name'],
            $payload['planType'],
            $payload['dateFrom'],
            $payload['dateTo'],
            $payload['limitAmount'],
            $payload['currency'],
            $payload['weight'],
            $payload['note'],
        );
    }
}
