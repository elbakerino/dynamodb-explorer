<?php

namespace App\RouteHandler;

use App\Repository\UserRepository;
use App\Services\AuthService;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Get("/user/{email}", name="user--profile")
 */
class UserProfile implements RequestHandlerInterface {
    use AuthChecker;

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
        if(!$this->isAuthorized($request)) {
            return (new Response(['error' => 'not authenticated']))->json(401);
        }
        $user = $this->getUser($request);
        $email = $request->getAttribute('email');
        if($user !== $email) {
            return (new Response(['error' => 'not authorized']))->json(401);
        }
        try {
            $user_data = $this->repository->getFullDetails($email);
        } catch(\RuntimeException $e) {
            throw $e;
        }
        return (new Response([
            'user' => $user_data['user'],
        ]))->json();
    }
}
