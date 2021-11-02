<?php declare(strict_types=1);

namespace App\Traits;

use App\Services\AuthService;
use DI\Annotation\Inject;
use Psr\Http\Message\ServerRequestInterface;

trait AuthChecker {
    /**
     * @Inject()
     */
    protected AuthService $auth_service;

    public function isAuthenticated(ServerRequestInterface $request): bool {
        return (bool)$request->getAttribute('authenticated');
    }

    public function getAuthUser(ServerRequestInterface $request): ?string {
        return $request->getAttribute('auth_payload')['uid'] ?? null;
    }

    public function getAuthError(ServerRequestInterface $request): ?string {
        return $request->getAttribute('auth_reason');
    }
}
