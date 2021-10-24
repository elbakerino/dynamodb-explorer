<?php declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\Response\Response;

class CorsMiddleware implements MiddlewareInterface {

    protected $origins_allowed = [];
    protected $headers_allowed = [];
    protected $headers_expose = [];
    protected $max_age = 1;

    public function __construct(array $origins_allowed = [], array $headers_allowed = [], array $headers_expose = [], int $max_age = 1) {
        $this->origins_allowed = $origins_allowed;
        $this->headers_allowed = $headers_allowed;
        $this->headers_expose = $headers_expose;

        $this->max_age = $max_age;
    }

    protected function setHeaders(ServerRequestInterface $request, ResponseInterface $response) {
        $current_origin = $request->getServerParams()['HTTP_ORIGIN'] ?? null;

        if($current_origin && in_array($current_origin, $this->origins_allowed, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $current_origin);
        }

        $response = $response->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, POST, GET, HEAD, DELETE, PUT, PATCH')
            ->withHeader('Access-Control-Max-Age', (string)$this->max_age);

        if(!empty($this->headers_allowed)) {
            $response = $response->withHeader('Access-Control-Allow-Headers', $this->headers_allowed);
        }

        if(!empty($this->headers_expose)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', $this->headers_expose);
        }

        return $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $response = (new Response())->createResponse(200);
        } else {
            $response = $handler->handle($request);
        }

        return $this->setHeaders($request, $response);
    }
}
