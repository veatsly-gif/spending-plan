<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminSpendingPlanMonthTabDto
{
    public function __construct(
        public string $monthKey,
        public string $label,
        public bool $active,
        public bool $needsAttention,
    ) {
    }

    /**
     * @return array{
     *     monthKey: string,
     *     label: string,
     *     active: bool,
     *     needsAttention: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'monthKey' => $this->monthKey,
            'label' => $this->label,
            'active' => $this->active,
            'needsAttention' => $this->needsAttention,
        ];
    }
}
