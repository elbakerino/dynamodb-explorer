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
 * @Post("/table/{table}/color/{color_name}", name="table-color--save")
 */
class TableColorSave implements RequestHandlerInterface {
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
            'update'
        );

        $color_name = $request->getAttribute('color_name');
        if(!$color_name) {
            return (new Response(['error' => 'color_name-param-must-be-set']))->json(400);
        }

        $body = $request->getParsedBody();
        $color_pk = $body->color_pk ?? null;
        $color_sk = $body->color_sk ?? null;
        $data = [];
        if($color_pk) {
            $data['color_pk'] = $color_pk;
        }
        if($color_sk) {
            $data['color_sk'] = $color_sk;
        }
        $full_data = $this->repository->saveTableColor($uuid, $color_name, $data);

        return (new Response([
            //'_size' => $full_data['size'],
            //'table' => $full_data['table'],
            'table' => $full_data,
        ]))->json();
    }
}
