<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;

final class NotificationCenter
{
    /**
     * @var array<string, NotificationTemplateRendererInterface>
     */
    private array $renderersByTemplate = [];

    /**
     * @var array<string, NotificationDeliveryHandlerInterface>
     */
    private array $handlersByDeliveryType = [];

    /**
     * @param iterable<NotificationTemplateRendererInterface> $templateRenderers
     * @param iterable<NotificationDeliveryHandlerInterface> $deliveryHandlers
     */
    public function __construct(
        iterable $templateRenderers,
        iterable $deliveryHandlers,
    ) {
        foreach ($templateRenderers as $renderer) {
            $this->renderersByTemplate[$this->normalizeTemplateCode($renderer->getTemplateCode())] = $renderer;
        }

        foreach ($deliveryHandlers as $handler) {
            $this->handlersByDeliveryType[$this->normalizeDeliveryType($handler->getDeliveryType())] = $handler;
        }
    }

    /**
     * @param array<string, scalar|null> $triggerPayload
     */
    public function dispatch(
        User $recipient,
        string $deliveryType,
        string $templateCode,
        array $triggerPayload,
        \DateTimeImmutable $occurredAt,
    ): void {
        $normalizedTemplate = $this->normalizeTemplateCode($templateCode);
        $renderer = $this->renderersByTemplate[$normalizedTemplate] ?? null;
        if (null === $renderer) {
            return;
        }

        $normalizedDeliveryType = $this->normalizeDeliveryType($deliveryType);
        $handler = $this->handlersByDeliveryType[$normalizedDeliveryType] ?? null;
        if (null === $handler) {
            return;
        }

        $envelope = $renderer->render($triggerPayload);
        $deliveryPayload = $envelope->payloadFor($normalizedDeliveryType);
        if (null === $deliveryPayload) {
            return;
        }

        $handler->deliver(new NotificationDelivery(
            $recipient,
            $normalizedTemplate,
            $deliveryPayload,
            $triggerPayload,
            $occurredAt
        ));
    }

    private function normalizeTemplateCode(string $templateCode): string
    {
        return mb_strtolower(trim($templateCode));
    }

    private function normalizeDeliveryType(string $deliveryType): string
    {
        $normalized = mb_strtolower(trim($deliveryType));

        return match ($normalized) {
            'pop-up', 'popup', 'pop_up', 'banner' => 'popup',
            default => $normalized,
        };
    }
}
