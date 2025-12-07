<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\TelegramAuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TelegramAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private TelegramAuthService $telegramAuthService
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Telegram-Init-Data');
    }

    public function authenticate(Request $request): Passport
    {
        $initData = $request->headers->get('X-Telegram-Init-Data');

        if (!$initData) {
            throw new AuthenticationException('No Telegram init data provided');
        }

        $user = $this->telegramAuthService->authenticate($initData);

        if (!$user) {
            throw new AuthenticationException('Invalid Telegram init data');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), function () use ($user) {
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
