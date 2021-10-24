<?php

namespace App\RouteHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get("/api-ping", name="api-ping")
 */
class ApiPing implements RequestHandlerInterface {
    protected string $explorer_name;

    public function __construct(string $explorer_name) {
        $this->explorer_name = $explorer_name;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        return (new Response([
            'dynamodb-explorer' => true,
            'explorer_name' => $this->explorer_name,
            'now' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        ]))->json();
    }
}
