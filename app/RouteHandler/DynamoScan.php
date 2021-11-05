<?php declare(strict_types=1);

namespace App\RouteHandler;

use Bemit\DynamoDB\DynamoService;
use DI\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Satellite\KernelRoute\Annotations\Get;
use Satellite\Response\Response;

/**
 * @Get("/dynamo-scan", name="dynamo-scan")
 */
class DynamoScan implements RequestHandlerInterface {

    /**
     * @Inject()
     */
    protected DynamoService $dynamo;

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        return (new Response(['error' => 'not implemented']))->json(501);

        $table_id = $request->getQueryParams()['table'] ?? null;
        if(!$table_id) {
            return (new Response(['error' => '`table` param is missing']))->json(400);
        }
        $result = $this->dynamo->client()->scan([
            'TableName' => $table_id,
        ]);
        return (new Response($result->toArray()))->json();
    }
}
