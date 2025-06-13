<?php

namespace App\Security;

use App\Service\Logs\MonologService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900;
    public const CACHE_PREFIX = 'login_attempts_';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MonologService $monolog,
        private readonly CacheItemPoolInterface $cache,
        private readonly RequestStack $requestStack,
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $email = $request->request->get('email', '');
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        $attempts = $this->incrementFailedAttempts($ip, $email);
        $this->monolog->loginAttempt($email, false, $ip);

        $this->monolog->securityEvent('login_failure_detailed', [
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'attempts_count' => $attempts,
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'is_locked' => $this->isBlocked($ip, $email)
        ]);

        if ($this->isBlocked($ip, $email)) {
            $this->monolog->securityEvent('ip_blocked_brute_force', [
                'ip' => $ip,
                'email' => $email,
                'attempts' => $attempts,
                'lockout_duration' => self::LOCKOUT_DURATION
            ]);

            $this->requestStack->getSession()->getFlashBag()->add('error', sprintf(
                'Trop de tentatives de connexion échouées. Votre IP est temporairement bloquée pendant %d minutes.',
                self::LOCKOUT_DURATION / 60
            ));
        } else {
            $remainingAttempts = self::MAX_ATTEMPTS - $attempts;
            $this->requestStack->getSession()->getFlashBag()->add('error', sprintf(
                'Identifiants incorrects. Il vous reste %d tentative(s) avant blocage temporaire.',
                $remainingAttempts
            ));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function incrementFailedAttempts(string $ip, string $email): int
    {
        $cacheKey = self::CACHE_PREFIX . md5($ip . $email);
        $cacheItem = $this->cache->getItem($cacheKey);

        $attempts = $cacheItem->get() ?? 0;
        $attempts++;

        $cacheItem->set($attempts);
        $cacheItem->expiresAfter(self::LOCKOUT_DURATION);
        $this->cache->save($cacheItem);

        return $attempts;
    }

    public function isBlocked(string $ip, string $email): bool
    {
        $cacheKey = self::CACHE_PREFIX . md5($ip . $email);
        $cacheItem = $this->cache->getItem($cacheKey);

        return ($cacheItem->get() ?? 0) >= self::MAX_ATTEMPTS;
    }
}
