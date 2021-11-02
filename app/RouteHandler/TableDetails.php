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
 * @Get("/table/{table}", name="table--details")
 */
class TableDetails implements RequestHandlerInterface {
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
        $uuid = $request->getAttribute('table');
        if(!$uuid) {
            return (new Response(['error' => 'table-param-must-be-set']))->json(400);
        }

        $user = $this->getAuthUser($request);
        $this->auth_service->requireAccessLevel(
            'user#' . $user,
            'table#' . $uuid,
            'read'
        );

        try {
            $full_data = $this->repository->getFullDetails($uuid);
        } catch(\RuntimeException $e) {
            if($e->getMessage() === 'db entry not found') {
                return (new Response(['error' => $e->getMessage()]))->json(404);
            }
            throw $e;
        }
        return (new Response([
            '_size' => $full_data['size'],
            'table' => $full_data['table'],
            'shares' => $full_data['shares'],
        ]))->json();
    }
}
