<?php

namespace App\RouteHandler;

use App\Repository\UserRepository;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Post("/login", name="user--login")
 */
class UserLogin implements RequestHandlerInterface {
    protected UserRepository $repository;
    protected AuthService $auth;

    public function __construct(UserRepository $repository, AuthService $auth) {
        $this->repository = $repository;
        $this->auth = $auth;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $body = $request->getParsedBody();
        $email = $body->email ?? null;
        if(!$email) {
            return (new Response(['error' => 'Missing `email`']))->json(400);
        }
        $password = $body->password ?? null;
        if(!$password) {
            return (new Response(['error' => 'Missing `password`']))->json(400);
        }
        try {
            $user = $this->repository->login(
                $email,
                $password,
            );
            $auth = $this->auth->createToken($email);
        } catch(\RuntimeException $e) {
            if($e->getMessage() === 'Password is incorrect.') {
                return (new Response([
                    'error' => $e->getMessage(),
                ]))->json(400);
            }

            if($e->getMessage() === 'Passcheck missing for user.') {
                return (new Response([
                    'error' => $e->getMessage(),
                ]))->json(500);
            }

            if($e->getMessage() === 'db entry not found') {
                return (new Response([
                    'error' => $e->getMessage(),
                ]))->json(404);
            }
            throw $e;
        }
        return (new Response([
            'user' => $user,
            'token' => $auth,
        ]))->json();
    }
}
