<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../Stubs/TestMiddleware.php';

use Atomic\Http\MiddlewareStack;
use Atomic\Http\MiddlewareStackInterface;
use Atomic\Http\OptimizedMiddlewareHandler;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Stubs\TestMiddleware;

class MiddlewareStackTest extends TestCase
{
    public function test_implements_middleware_stack_interface()
    {
        $stack = new MiddlewareStack();
        $this->assertInstanceOf(MiddlewareStackInterface::class, $stack);
    }

    public function test_compile_with_empty_stack_returns_original_handler()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $stack = new MiddlewareStack();

        $result = $stack->compile($handler);

        $this->assertSame($handler, $result);
    }

    public function test_compile_caches_pipeline()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $stack = new MiddlewareStack();

        $result1 = $stack->compile($handler);
        $result2 = $stack->compile($handler);

        $this->assertSame($result1, $result2);
    }

    public function test_compile_caches_per_handler()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn($response);
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->method('handle')->willReturn($response);

        $stack = new MiddlewareStack();
        $stack->add(new TestMiddleware('m1'));

        $compiled1a = $stack->compile($handler1);
        $compiled2a = $stack->compile($handler2);

        $this->assertNotSame($compiled1a, $compiled2a, 'Different handlers should yield distinct compiled pipelines');

        // Subsequent calls should return the same cached instances per handler
        $compiled1b = $stack->compile($handler1);
        $compiled2b = $stack->compile($handler2);

        $this->assertSame($compiled1a, $compiled1b);
        $this->assertSame($compiled2a, $compiled2b);

        // Pipelines should function
        $this->assertInstanceOf(ResponseInterface::class, $compiled1a->handle($request));
        $this->assertInstanceOf(ResponseInterface::class, $compiled2a->handle($request));
    }

    public function test_add_invalidates_compiled_pipeline_cache()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $stack = new MiddlewareStack();

        // First compilation
        $result1 = $stack->compile($handler);

        // Add middleware (should invalidate cache)
        $stack->add($middleware);

        // Second compilation should return different instance
        $result2 = $stack->compile($handler);

        $this->assertNotSame($result1, $result2);
    }

    public function test_compile_with_single_middleware_instance()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->with($request, $this->isInstanceOf(RequestHandlerInterface::class))
            ->willReturn($response);

        $stack = new MiddlewareStack();
        $stack->add($middleware);

        $compiledPipeline = $stack->compile($handler);
        $result = $compiledPipeline->handle($request);

        $this->assertSame($response, $result);
        $this->assertInstanceOf(OptimizedMiddlewareHandler::class, $compiledPipeline);
    }

    public function test_compile_with_multiple_middleware_instances()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware1 = new TestMiddleware('middleware1');
        $middleware2 = new TestMiddleware('middleware2');

        $stack = new MiddlewareStack();
        $stack->add($middleware1);
        $stack->add($middleware2);

        $compiledPipeline = $stack->compile($handler);
        $result = $compiledPipeline->handle($request);

        // Verify middleware were called in correct order (FIFO - first added, first executed)
        $this->assertEquals(['middleware1', 'middleware2'], TestMiddleware::$executionOrder);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_compile_with_middleware_class_names_without_container()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $stack = new MiddlewareStack();

        $stack->add(TestMiddleware::class);

        $compiledPipeline = $stack->compile($handler);

        $this->assertInstanceOf(OptimizedMiddlewareHandler::class, $compiledPipeline);
    }

    public function test_compile_with_middleware_class_names_using_container()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = $this->createMock(MiddlewareInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(TestMiddleware::class)
            ->willReturn($middleware);

        $stack = new MiddlewareStack($container);
        $stack->add(TestMiddleware::class);

        $compiledPipeline = $stack->compile($handler);

        $this->assertInstanceOf(OptimizedMiddlewareHandler::class, $compiledPipeline);
    }

    public function test_middleware_instance_caching()
    {
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler2 = $this->createMock(RequestHandlerInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once()) // Should only be called once due to caching
            ->method('get')
            ->with(TestMiddleware::class)
            ->willReturn($this->createMock(MiddlewareInterface::class));

        $stack = new MiddlewareStack($container);
        $stack->add(TestMiddleware::class);

        // Compile twice with different handlers
        $stack->compile($handler1);
        $stack->compile($handler2);

        // Container should only be called once due to instance caching
    }

    public function test_mixed_middleware_types()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middlewareInstance = $this->createMock(MiddlewareInterface::class);

        $stack = new MiddlewareStack();
        $stack->add($middlewareInstance);
        $stack->add(TestMiddleware::class);

        $compiledPipeline = $stack->compile($handler);

        $this->assertInstanceOf(OptimizedMiddlewareHandler::class, $compiledPipeline);
    }

    public function test_middleware_stack_is_final()
    {
        $reflection = new \ReflectionClass(MiddlewareStack::class);
        $this->assertTrue($reflection->isFinal());
    }

    protected function setUp(): void
    {
        TestMiddleware::reset();
    }

    protected function tearDown(): void
    {
        TestMiddleware::reset();
    }
}
