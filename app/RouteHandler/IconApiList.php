<?php

namespace App\RouteHandler;

use App\Services\Icon1Service;
use DI\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get(name="icons", path="/icons")
 */
class IconApiList implements RequestHandlerInterface {
    /**
     * @Inject()
     */
    protected Icon1Service $icons;

    /*protected function __construct(Icon1Service $icons) {
        $this->icons = $icons;
    }*/

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $provider = $request->getQueryParams()['provider'] ?? null;
        if(!$provider) {
            return (new Response($this->icons->getProvider()))->json();
        }
        $search = $request->getQueryParams()['search'] ?? null;
        $page = $request->getQueryParams()['page'] ?? 1;
        $per_page = $request->getQueryParams()['per_page'] ?? 25;
        return (new Response($this->icons->searchInList($provider, $page, $per_page, $search)))->json();
    }
}
