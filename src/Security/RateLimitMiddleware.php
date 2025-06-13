<?php

namespace App\Security;

use App\Service\Logs\MonologService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function __construct(
        private readonly LoginFailureHandler $failureHandler,
        private readonly MonologService $monolog
    ) {}

    public function checkRateLimit(Request $request): ?Response
    {
        $email = $request->request->get('email', '');
        $ip = $request->getClientIp();

        if ($this->failureHandler->isBlocked($ip, $email)) {
            $this->monolog->securityEvent('blocked_login_attempt', [
                'ip' => $ip,
                'email' => $email,
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            return new Response('Trop de tentatives. Veuillez réessayer plus tard.', 429);
        }

        return null;
    }
}
