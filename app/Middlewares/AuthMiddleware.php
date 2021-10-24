<?php declare(strict_types=1);

namespace App\Middlewares;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\Response\Response;

class AuthMiddleware implements MiddlewareInterface {
    protected AuthService $auth;

    public function __construct(AuthService $auth) {
        $this->auth = $auth;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $server_params = $request->getServerParams();
        $bearer_prefix_length = strlen('Bearer ');
        if(
            isset($server_params['HTTP_AUTHORIZATION']) &&
            strlen($server_params['HTTP_AUTHORIZATION']) > $bearer_prefix_length &&
            trim(substr($server_params['HTTP_AUTHORIZATION'], $bearer_prefix_length)) !== ''
        ) {
            $validate_result = $this->auth->parse(substr($server_params['HTTP_AUTHORIZATION'], $bearer_prefix_length));
            if($validate_result) {
                $header = $validate_result->getHeader();
                $request = $request
                    ->withAttribute('auth_payload', $validate_result->getPayload())
                    ->withAttribute('auth_header', $header)
                    ->withAttribute('authenticated', isset($header['typ']));
            }
        }
        //error_log(json_encode($request->getAttribute('auth_payload')));
        //error_log(json_encode($request->getAttribute('auth_header')));
        //error_log(json_encode($request->getAttribute('authenticated')));
        return $handler->handle($request);
    }
}
