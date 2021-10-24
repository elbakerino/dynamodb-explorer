<?php declare(strict_types=1);

namespace App\Traits;

use Psr\Http\Message\ServerRequestInterface;

trait AuthChecker {
    public function isAuthorized(ServerRequestInterface $request): bool {
        return (bool)$request->getAttribute('authenticated');
    }

    public function getUser(ServerRequestInterface $request): ?string {
        return $request->getAttribute('auth_payload')['uid'] ?? null;
    }
}
