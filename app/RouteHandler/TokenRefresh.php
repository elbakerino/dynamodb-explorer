<?php

namespace App\RouteHandler;

use App\Repository\TableRepository;
use App\Services\AuthService;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get("/token", name="token")
 */
class TokenRefresh implements RequestHandlerInterface {
    use AuthChecker;

    protected AuthService $auth;

    protected TableRepository $repository;

    public function __construct(TableRepository $repository, AuthService $auth) {
        $this->repository = $repository;
        $this->auth = $auth;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        if(!$this->isAuthenticated($request)) {
            return (new Response([
                'error' => 'not authenticated',
                'details' => $this->getAuthError($request),
            ]))->json(401);
        }
        $user = $this->getAuthUser($request);

        return (new Response([
            'token' => $this->auth->createToken($user),
        ]))->json();
    }
}
