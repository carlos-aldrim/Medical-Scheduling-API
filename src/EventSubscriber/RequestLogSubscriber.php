<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestLogSubscriber implements EventSubscriberInterface
{
    private array $startTimes = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onRequest',  200],
            KernelEvents::RESPONSE => ['onResponse', -200],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = spl_object_id($event->getRequest());
        $this->startTimes[$requestId] = microtime(true);

        $request = $event->getRequest();

        $this->logger->info('http.request', [
            'method'     => $request->getMethod(),
            'path'       => $request->getPathInfo(),
            'query'      => $request->getQueryString(),
            'ip'         => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId  = spl_object_id($event->getRequest());
        $durationMs = isset($this->startTimes[$requestId])
            ? round((microtime(true) - $this->startTimes[$requestId]) * 1000, 2)
            : null;

        unset($this->startTimes[$requestId]);

        $request  = $event->getRequest();
        $response = $event->getResponse();
        $status   = $response->getStatusCode();

        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default        => 'info',
        };

        $this->logger->$level('http.response', [
            'method'      => $request->getMethod(),
            'path'        => $request->getPathInfo(),
            'status'      => $status,
            'duration_ms' => $durationMs,
            'ip'          => $request->getClientIp(),
        ]);
    }
}
