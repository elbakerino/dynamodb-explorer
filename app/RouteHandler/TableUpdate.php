<?php

namespace App\RouteHandler;

use App\Repository\TableRepository;
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
        // todo: auth check

        $uuid = $request->getAttribute('table');
        if(!$uuid) {
            return (new Response(['error' => 'table-param-must-be-set']))->json(400);
        }

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
        $full_data = $this->repository->update($uuid, $data);

        return (new Response([
            //'_size' => $full_data['size'],
            //'table' => $full_data['table'],
            'table' => $full_data,
        ]))->json();
    }
}
