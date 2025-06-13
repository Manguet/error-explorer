<?php

namespace App\Tests\Unit\Security;

use App\Security\LoginSuccessHandler;
use App\Service\Logs\MonologService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LoginSuccessHandlerTest extends TestCase
{
    private LoginSuccessHandler $handler;
    private UrlGeneratorInterface $urlGenerator;
    private MonologService $monolog;
    private CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->monolog = $this->createMock(MonologService::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->handler = new LoginSuccessHandler(
            $this->urlGenerator,
            $this->monolog,
            $this->cache
        );
    }

    public function testOnAuthenticationSuccessRegularUser(): void
    {
        $request = $this->createSuccessRequest();
        $token = $this->createTokenWithUser(['ROLE_USER']);

        // Expectations pour les logs
        $this->monolog->expects($this->once())
            ->method('loginAttempt')
            ->with('test@example.com', true, '192.168.1.1');

        $this->monolog->expects($this->once())
            ->method('businessEvent')
            ->with('user_login_success', $this->isType('array'));

        // Reset du cache des tentatives échouées
        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with($this->stringContains('login_attempts_'));

        // Redirection vers dashboard
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('dashboard_index')
            ->willReturn('/dashboard');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('/dashboard', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessAdminUser(): void
    {
        $request = $this->createSuccessRequest();
        $token = $this->createTokenWithUser(['ROLE_ADMIN', 'ROLE_USER']);

        // Redirection vers admin dashboard
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('admin_dashboard')
            ->willReturn('/admin');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $this->assertSame('/admin', $response->getTargetUrl());
    }

    private function createSuccessRequest(): Request
    {
        $request = Request::create('/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 Test Browser');

        // Mock de la session
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('session_123');
        $request->setSession($session);

        return $request;
    }

    private function createTokenWithUser(array $roles): TokenInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test@example.com');
        $user->method('getRoles')->willReturn($roles);

        // Mock pour getId() si c'est notre entité User
        if (method_exists($user, 'getId')) {
            $user->method('getId')->willReturn(123);
        }

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
