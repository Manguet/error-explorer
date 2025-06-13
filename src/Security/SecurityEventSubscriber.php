<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimitMiddleware $rateLimitMiddleware
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getPathInfo() === '/login' && $request->isMethod('POST')) {
            $response = $this->rateLimitMiddleware->checkRateLimit($request);

            if ($response) {
                $event->setResponse($response);
            }
        }
    }
}
