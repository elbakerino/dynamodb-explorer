<?php

namespace App\RouteHandler;

use App\Repository\UserRepository;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Post("/user/{email}", name="user--update")
 */
class UserUpdate implements RequestHandlerInterface {
    use AuthChecker;

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
        if(!$this->isAuthenticated($request)) {
            return (new Response(['error' => 'not authenticated']))->json(401);
        }
        $user = $this->getAuthUser($request);
        $email = $request->getAttribute('email');
        if($user !== $email) {
            return (new Response(['error' => 'not authorized']))->json(401);
        }
        $body = $request->getParsedBody();
        $password = $body->password ?? null;
        $data = [];
        if($password) {
            $data['password'] = $password;
        }
        $user = $this->repository->update($email, $data);
        return (new Response([
            'user' => $user,
        ]))->json();
    }
}
