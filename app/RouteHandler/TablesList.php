<?php

namespace App\RouteHandler;

use App\Repository\TableRepository;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get("/tables", name="tables--list")
 */
class TablesList implements RequestHandlerInterface {
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
        if(!$this->isAuthorized($request)) {
            return (new Response(['error' => 'not authenticated']))->json(401);
        }
        $user = $this->getUser($request);
        $tables = $this->repository->listForUser($user, 'asc', 25);
        return (new Response([
            'tables' => $tables,
        ]))->json();
    }
}
