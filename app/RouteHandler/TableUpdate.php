<?php

namespace App\RouteHandler;

use App\Repository\TableRepository;
use App\Traits\AuthChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\KernelRoute\Annotations\Post;
use Satellite\Response\Response;

/**
 * @Post("/table/{table}", name="table--update")
 */
class TableUpdate implements RequestHandlerInterface {
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

        $body = $request->getParsedBody();
        $data = [];
        $name = $body->name ?? null;
        if($name) {
            $data['name'] = $name;
        }
        $schema = $body->schema ?? null;
        if($schema) {
            $data['schema'] = $schema;
        }
        $exampleData = $body->exampleData ?? null;
        if($exampleData) {
            $data['exampleData'] = $exampleData;
        }
        $entities = $body->entities ?? null;
        if($entities) {
            $data['entities'] = $entities;
        }
        $full_data = $this->repository->update($uuid, $data);

        return (new Response([
            //'_size' => $full_data['size'],
            //'table' => $full_data['table'],
            'table' => $full_data,
        ]))->json();
    }
}
