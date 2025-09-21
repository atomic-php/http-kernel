<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/Support/ArrayContainer.php';

use Atomic\Http\Kernel;
use Atomic\Http\MiddlewareStack;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Integration\Support\ArrayContainer;

class ContainerResolutionIntegrationTest extends \PHPUnit\Framework\TestCase
{
    public function test_class_string_middleware_resolves_via_container(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/');

        $mwClass = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                return $response->withHeader('X-From-Container', 'true');
            }
        };

        $container = new ArrayContainer([
            'ContainerMiddleware' => $mwClass,
        ]);

        $stack = new MiddlewareStack($container);
        $stack->add('ContainerMiddleware');

        $finalHandler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $kernel = new Kernel($finalHandler, $stack);
        $response = $kernel->handle($request);

        $this->assertSame('true', $response->getHeaderLine('X-From-Container'));
    }
}
