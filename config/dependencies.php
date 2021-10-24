<?php

use function DI\autowire;
use function DI\get;
use function DI\create;

return static function($config) {
    $is_prod = $_ENV['env'] === 'prod';
    $dyn_explorer_table = $_ENV['DYN_EXPLORER_TABLE'] ?? 'dyn_explorer';
    return [
        Satellite\SatelliteAppInterface::class => autowire(Satellite\SatelliteApp::class),
        //
        // event handler
        Satellite\Event\EventListenerInterface::class => autowire(Satellite\Event\EventListener::class),
        Psr\EventDispatcher\ListenerProviderInterface::class => get(Satellite\Event\EventListenerInterface::class),
        Satellite\Event\EventDispatcher::class => autowire()
            ->constructorParameter('listener', get(Psr\EventDispatcher\ListenerProviderInterface::class))
            ->constructorParameter('invoker', get(Invoker\InvokerInterface::class)),
        Psr\EventDispatcher\EventDispatcherInterface::class => get(Satellite\Event\EventDispatcher::class),
        //
        // HTTP Servers & Clients
        Nyholm\Psr7\Factory\Psr17Factory::class => autowire(),
        Psr\Http\Message\RequestFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        Psr\Http\Message\ResponseFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        Psr\Http\Message\ServerRequestFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        Psr\Http\Message\StreamFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        Psr\Http\Message\UploadedFileFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        Psr\Http\Message\UriFactoryInterface::class => get(Nyholm\Psr7\Factory\Psr17Factory::class),
        //
        // annotations
        Doctrine\Common\Annotations\IndexedReader::class => autowire()
            ->constructorParameter('reader', get(Doctrine\Common\Annotations\AnnotationReader::class)),
        Doctrine\Common\Annotations\CachedReader::class => autowire()
            ->constructorParameter('reader', get(Doctrine\Common\Annotations\IndexedReader::class))
            ->constructorParameter(
                'cache',
                create(Doctrine\Common\Cache\ChainCache::class)
                    ->constructor([
                        create(Doctrine\Common\Cache\ArrayCache::class),
                        get(Doctrine\Common\Cache\PhpFileCache::class),
                    ])
            ),
        Doctrine\Common\Annotations\Reader::class => $is_prod ?
            get(Doctrine\Common\Annotations\CachedReader::class) :
            get(Doctrine\Common\Annotations\IndexedReader::class),
        Orbiter\AnnotationsUtil\CodeInfo::class => autowire()
            ->constructorParameter('file_cache', $is_prod ? $config['dir_tmp'] . '/codeinfo.cache' : null),
        Orbiter\AnnotationsUtil\AnnotationDiscovery::class => autowire(),
        Orbiter\AnnotationsUtil\AnnotationReader::class => autowire(),
        //
        // routing
        Satellite\Response\ResponsePipe::class => autowire(),
        Satellite\KernelRoute\Router::class => autowire(Satellite\KernelRoute\Router::class)
            ->constructorParameter('cache', $is_prod ? $config['dir_tmp'] . '/route.cache' : null),
        //
        // caches
        Doctrine\Common\Cache\PhpFileCache::class => autowire()
            ->constructorParameter('directory', $config['dir_tmp'] . '/common'),
        //
        // logger
        Psr\Log\LoggerInterface::class => autowire(Monolog\Logger::class)
            ->constructor('default')
            ->method('pushHandler', get(Monolog\Handler\StreamHandler::class)),
        Monolog\Handler\StreamHandler::class => autowire()
            ->constructor('php://stdout'),
        // App Stuff
        App\Commands\Dynamo::class => autowire()
            ->constructorParameter('table_dir', __DIR__ . '/../sql'),
        App\Services\DynamoService::class => autowire()
            ->constructorParameter('region', $_ENV['DYNAMO_DB_REGION'] ?? 'eu-central-1')
            ->constructorParameter('dynamo_key', $_ENV['DYNAMO_DB_KEY'])
            ->constructorParameter('dynamo_secret', $_ENV['DYNAMO_DB_SECRET'])
            ->constructorParameter('endpoint', $_ENV['DYNAMO_DB_ENDPOINT'] ?? null),
        App\Middlewares\CorsMiddleware::class => autowire()
            ->constructor(
                [
                    'https://dynamodb-visualizer.bemit.codes',
                    ...(isset($_ENV['CORS_ORIGINS']) && $_ENV['CORS_ORIGINS'] ? explode(',', $_ENV['CORS_ORIGINS']) : []),
                ],
                [
                    'Content-Type',
                    'Accept',
                    'AUTHORIZATION',
                    'X-Requested-With',
                ],
                [
                    'Content-Range',
                ],
                7200,
            ),
        App\Services\AuthService::class => autowire()
            ->constructorParameter('secret', $_ENV['AUTH_SECRET'])
            ->constructorParameter('issuer', $_ENV['AUTH_ISSUER']),
        App\Repository\UserRepository::class => autowire()
            ->constructorParameter('table', $dyn_explorer_table)
            ->constructorParameter('app_salt', $_ENV['APP_SALT']),
        App\Repository\TableRepository::class => autowire()
            ->constructorParameter('table', $dyn_explorer_table),
        App\RouteHandler\ApiPing::class => autowire()
            ->constructorParameter('explorer_name', $_ENV['EXPLORER_NAME'] ?? 'Localhost Explorer'),
    ];
};
