<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atomic\Http\Kernel;
use Atomic\Http\MiddlewareStack;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PipelineIntegrationTest extends \PHPUnit\Framework\TestCase
{
    public function test_pipeline_modifies_headers_and_body_end_to_end(): void
    {
        $psr17 = new Psr17Factory;
        $request = $psr17->createServerRequest('GET', '/hello');

        $finalHandler = new class($psr17) implements RequestHandlerInterface
        {
            public function __construct(protected Psr17Factory $f) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['X-Final' => 'yes'], 'base');
            }
        };

        $headerMiddleware = new class implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);

                return $response->withHeader('X-MW', '1');
            }
        };

        $bodyMiddleware = new class implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                $contents = (string) $response->getBody();

                return $response->withBody((new Psr17Factory)->createStream($contents.'-modified'));
            }
        };

        $stack = new MiddlewareStack;
        $stack->add($headerMiddleware);
        $stack->add($bodyMiddleware);
        $kernel = new Kernel($finalHandler, $stack);

        $response = $kernel->handle($request);

        $this->assertSame('yes', $response->getHeaderLine('X-Final'));
        $this->assertSame('1', $response->getHeaderLine('X-MW'));
        $this->assertSame('base-modified', (string) $response->getBody());
    }

    public function test_short_circuit_middleware_skips_final_handler(): void
    {
        $psr17 = new Psr17Factory;
        $request = $psr17->createServerRequest('GET', '/short');

        $finalHandlerCalled = false;
        $finalHandler = new class($finalHandlerCalled) implements RequestHandlerInterface
        {
            public function __construct(protected bool &$called) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new Response(200);
            }
        };

        $shortCircuit = new class implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(418, ['X-Short' => 'true'], 'teapot');
            }
        };

        $stack = new MiddlewareStack;
        $stack->add($shortCircuit);
        $kernel = new Kernel($finalHandler, $stack);

        $response = $kernel->handle($request);
        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaderLine('X-Short'));
        $this->assertSame('teapot', (string) $response->getBody());
        $this->assertFalse($finalHandlerCalled, 'Final handler should not be called');
    }
}
