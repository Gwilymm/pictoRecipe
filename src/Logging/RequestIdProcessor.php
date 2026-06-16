<?php

namespace App\Logging;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestIdProcessor
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        $requestId = $request?->attributes->get('request_id');

        if (!is_string($requestId) || $requestId === '') {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, [
            'request_id' => $requestId,
        ]));
    }
}
