<?php

namespace App\RouteHandler;

use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Post("/user", name="user--create")
 */
class UserCreate implements RequestHandlerInterface {
    protected UserRepository $repository;

    public function __construct(UserRepository $repository) {
        $this->repository = $repository;
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
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return (new Response(['error' => 'Field `email` is invalid']))->json(400);
        }
        $password = $body->password ?? null;
        if(!$password) {
            return (new Response(['error' => 'Missing `password`']))->json(400);
        }
        if(strlen($password) < 8) {
            return (new Response(['error' => 'Password min. length: 8']))->json(400);
        }
        $user = $this->repository->create(
            $email,
            $password,
        );
        return (new Response([
            'user' => $user,
        ]))->json();
    }
}
