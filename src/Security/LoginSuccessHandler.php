<?php

namespace App\Security;

use App\Service\Logs\MonologService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MonologService $monolog,
        private readonly CacheItemPoolInterface $cache
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        $email = $user?->getUserIdentifier();
        $ip = $request->getClientIp();

        $this->resetFailedAttempts($ip, $email);

        $this->monolog->loginAttempt($email, true, $ip);

        $this->monolog->businessEvent('user_login_success', [
            'user_id' => method_exists($user, 'getId') ? $user?->getId() : null,
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $request->headers->get('User-Agent'),
            'session_id' => $request->getSession()->getId()
        ]);

        if (in_array('ROLE_ADMIN', $user?->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('dashboard_index'));
    }

    private function resetFailedAttempts(string $ip, string $email): void
    {
        $cacheKey = LoginFailureHandler::CACHE_PREFIX . md5($ip . $email);
        $this->cache->deleteItem($cacheKey);
    }
}
