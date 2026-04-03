<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminSpendingPlanPopupDto
{
    public function __construct(
        public bool $show,
        public string $title,
        public string $message,
        public string $monthKey,
        public string $template = '',
        public array $actions = [],
    ) {
    }

    /**
     * @return array{
     *     show: bool,
     *     title: string,
     *     message: string,
     *     monthKey: string,
     *     template: string,
     *     actions: list<array{code: string, label: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'show' => $this->show,
            'title' => $this->title,
            'message' => $this->message,
            'monthKey' => $this->monthKey,
            'template' => $this->template,
            'actions' => $this->actions,
        ];
    }
}
