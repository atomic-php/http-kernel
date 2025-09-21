<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Http\OptimizedMiddlewareHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OptimizedMiddlewareHandlerTest extends TestCase
{
    public function test_implements_request_handler_interface()
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $next = $this->createMock(RequestHandlerInterface::class);

        $handler = new OptimizedMiddlewareHandler($middleware, $next);

        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);
    }

    public function test_handle_delegates_to_middleware_process()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $next = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->with($request, $next)
            ->willReturn($response);

        $handler = new OptimizedMiddlewareHandler($middleware, $next);
        $result = $handler->handle($request);

        $this->assertSame($response, $result);
    }

    public function test_handle_propagates_exceptions_from_middleware()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $next = $this->createMock(RequestHandlerInterface::class);
        $exception = new \RuntimeException('Middleware error');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')
            ->with($request, $next)
            ->willThrowException($exception);

        $handler = new OptimizedMiddlewareHandler($middleware, $next);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware error');

        $handler->handle($request);
    }

    public function test_middleware_can_call_next_handler()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        // Create a middleware that calls the next handler
        $middleware = new class implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $handler = new OptimizedMiddlewareHandler($middleware, $next);
        $result = $handler->handle($request);

        $this->assertSame($response, $result);
    }

    public function test_middleware_can_modify_request_before_next()
    {
        $originalRequest = $this->createMock(ServerRequestInterface::class);
        $modifiedRequest = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($modifiedRequest) // Should receive the modified request
            ->willReturn($response);

        // Create a middleware that modifies the request
        $middleware = new class($modifiedRequest) implements MiddlewareInterface
        {
            public function __construct(protected ServerRequestInterface $modifiedRequest) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($this->modifiedRequest);
            }
        };

        $handler = new OptimizedMiddlewareHandler($middleware, $next);
        $result = $handler->handle($originalRequest);

        $this->assertSame($response, $result);
    }

    public function test_middleware_can_modify_response_after_next()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $originalResponse = $this->createMock(ResponseInterface::class);
        $modifiedResponse = $this->createMock(ResponseInterface::class);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->method('handle')->willReturn($originalResponse);

        // Create a middleware that modifies the response
        $middleware = new class($modifiedResponse) implements MiddlewareInterface
        {
            public function __construct(protected ResponseInterface $modifiedResponse) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);

                return $this->modifiedResponse; // Return modified response
            }
        };

        $handler = new OptimizedMiddlewareHandler($middleware, $next);
        $result = $handler->handle($request);

        $this->assertSame($modifiedResponse, $result);
    }

    public function test_middleware_can_short_circuit_pipeline()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects($this->never())->method('handle'); // Should never be called

        // Create a middleware that short-circuits
        $middleware = new class($response) implements MiddlewareInterface
        {
            public function __construct(protected ResponseInterface $response) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                // Don't call the next handler, return response directly
                return $this->response;
            }
        };

        $handler = new OptimizedMiddlewareHandler($middleware, $next);
        $result = $handler->handle($request);

        $this->assertSame($response, $result);
    }

    public function test_optimized_middleware_handler_is_final()
    {
        $reflection = new \ReflectionClass(OptimizedMiddlewareHandler::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_optimized_middleware_handler_is_readonly()
    {
        $reflection = new \ReflectionClass(OptimizedMiddlewareHandler::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
