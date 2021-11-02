<?php

namespace App\RouteHandler;

use App\Repository\TableRepository;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Post("/table", name="table--create")
 */
class TableCreate implements RequestHandlerInterface {
    use AuthChecker;

    protected TableRepository $repository;

    public function __construct(TableRepository $repository) {
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

        $body = $request->getParsedBody();
        $name = $body->name ?? null;
        if(!$name) {
            return (new Response(['error' => 'Missing `name`']))->json(400);
        }

        $table = $this->repository->create($name, 'user#' . $user);
        return (new Response([
            'table' => $table,
        ]))->json();
    }
}
