<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestIdSubscriber implements EventSubscriberInterface
{
    private const HEADER = 'X-Request-Id';
    private const ATTRIBUTE = 'request_id';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $this->sanitizeRequestId($request->headers->get(self::HEADER))
            ?? $this->generateRequestId();

        $request->attributes->set(self::ATTRIBUTE, $requestId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (is_string($requestId) && $requestId !== '') {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }

    private function sanitizeRequestId(?string $requestId): ?string
    {
        if ($requestId === null) {
            return null;
        }

        $requestId = trim($requestId);
        if ($requestId === '' || strlen($requestId) > 128) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._-]+$/', $requestId) === 1 ? $requestId : null;
    }

    private function generateRequestId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
