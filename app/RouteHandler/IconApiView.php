<?php

namespace App\RouteHandler;

use App\Services\Icon1Service;
use DI\Annotation\Inject;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get(name="icon", path="/icon/{icon_provider}/{icon}")
 */
class IconApiView implements RequestHandlerInterface {
    /**
     * @Inject()
     */
    protected Icon1Service $icons;

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $icon_provider = $request->getAttribute('icon_provider');
        $icon_id = $request->getAttribute('icon');

        if($icon_provider !== 'simple-icons') {
            return (new Response())->html(501, 'Not Implemented');
        }
        $as_json = in_array('application/json', $request->getHeader('Content-Type'));
        $svg = file_get_contents(__DIR__ . '/../../vendor/simple-icons/simple-icons/icons/' . $icon_id . '.svg');
        if(!$svg) {
            if($as_json) {
                return (new Response(['error' => 'Not Found']))->json(404, 'Not Found');
            }
            return (new Response(''))->html(404, 'Not Found')
                ->withHeader('Content-Type', 'image/svg+xml');
        }
        if($as_json) {
            return (new Response([
                'data' => $svg,
            ]))->json(200)
                ->withHeader('Cache-Control', 'public, max-age=604800, immutable');
        }
        return (new Response($svg))->html(200)
            ->withHeader('Cache-Control', 'public, max-age=604800, immutable')
            ->withHeader('Content-Type', 'image/svg+xml');
    }
}
