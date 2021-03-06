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
 * @Post("/table/{table}/preset/{preset_name}", name="table-preset--save")
 */
class TablePresetSave implements RequestHandlerInterface {
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

        $preset_name = $request->getAttribute('preset_name');
        if(!$preset_name) {
            return (new Response(['error' => 'preset_name-param-must-be-set']))->json(400);
        }

        $body = $request->getParsedBody();
        $display_keys = $body->display_keys ?? null;
        $data = [];
        if($display_keys) {
            $data['display_keys'] = $display_keys;
        }
        $full_data = $this->repository->saveTablePreset($uuid, $preset_name, $data);

        return (new Response([
            //'_size' => $full_data['size'],
            //'table' => $full_data['table'],
            'table' => $full_data,
        ]))->json();
    }
}
