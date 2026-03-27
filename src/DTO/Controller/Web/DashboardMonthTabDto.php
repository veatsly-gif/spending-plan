<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardMonthTabDto
{
    public function __construct(
        public string $monthKey,
        public string $label,
        public bool $active,
    ) {
    }

    /**
     * @return array{monthKey: string, label: string, active: bool}
     */
    public function toArray(): array
    {
        return [
            'monthKey' => $this->monthKey,
            'label' => $this->label,
            'active' => $this->active,
        ];
    }
}
