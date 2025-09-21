<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__.'/../Stubs/TestMiddlewareStack.php';

use Atomic\Http\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Stubs\TestMiddlewareStack;

class KernelTest extends TestCase
{
    public function test_handle_delegates_to_compiled_pipeline(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledHandler = $this->createMock(RequestHandlerInterface::class);

        $compiledHandler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $stack = new TestMiddlewareStack($compiledHandler);
        $kernel = new Kernel($finalHandler, $stack);

        $result = $kernel->handle($request);
        $this->assertSame($response, $result);
    }

    public function test_constructor_compiles_middleware_stack_immediately(): void
    {
        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline = $this->createMock(RequestHandlerInterface::class);

        $stack = new TestMiddlewareStack($compiledPipeline);

        // Compilation should happen during construction
        $kernel = new Kernel($finalHandler, $stack);

        // Verify the stack was compiled by checking if it was called
        $this->assertTrue($stack->wasCompileCalled());
    }

    public function test_handle_uses_compiled_pipeline_not_original_handler(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $originalHandler = $this->createMock(RequestHandlerInterface::class);
        $originalHandler->expects($this->never())->method('handle');

        $compiledPipeline = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $stack = new TestMiddlewareStack($compiledPipeline);
        $kernel = new Kernel($originalHandler, $stack);

        $result = $kernel->handle($request);
        $this->assertSame($response, $result);
    }

    public function test_handle_propagates_exceptions_from_compiled_pipeline(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = new \RuntimeException('Pipeline error');

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline->method('handle')
            ->with($request)
            ->willThrowException($exception);

        $stack = new TestMiddlewareStack($compiledPipeline);
        $kernel = new Kernel($finalHandler, $stack);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline error');

        $kernel->handle($request);
    }

    public function test_multiple_handle_calls_use_same_compiled_pipeline(): void
    {
        $request1 = $this->createMock(ServerRequestInterface::class);
        $request2 = $this->createMock(ServerRequestInterface::class);
        $response1 = $this->createMock(ResponseInterface::class);
        $response2 = $this->createMock(ResponseInterface::class);

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(function ($request) use ($request1, $response1, $response2) {
                return $request === $request1 ? $response1 : $response2;
            });

        $stack = new TestMiddlewareStack($compiledPipeline);
        $kernel = new Kernel($finalHandler, $stack);

        $result1 = $kernel->handle($request1);
        $result2 = $kernel->handle($request2);

        $this->assertSame($response1, $result1);
        $this->assertSame($response2, $result2);

        // Verify compile was only called once
        $this->assertEquals(1, $stack->getCompileCallCount());
    }

    public function test_kernel_implements_request_handler_interface(): void
    {
        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledHandler = $this->createMock(RequestHandlerInterface::class);
        $stack = new TestMiddlewareStack($compiledHandler);

        $kernel = new Kernel($finalHandler, $stack);

        $this->assertInstanceOf(RequestHandlerInterface::class, $kernel);
    }

    public function test_kernel_is_final_class(): void
    {
        $reflection = new \ReflectionClass(Kernel::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_kernel_is_readonly_class(): void
    {
        $reflection = new \ReflectionClass(Kernel::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_handle_with_different_request_types(): void
    {
        $requests = [
            $this->createMockRequest('GET', '/api/users'),
            $this->createMockRequest('POST', '/api/users'),
            $this->createMockRequest('DELETE', '/api/users/1'),
        ];

        $responses = array_map(fn () => $this->createMock(ResponseInterface::class), $requests);

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline = $this->createMock(RequestHandlerInterface::class);
        $compiledPipeline->method('handle')
            ->willReturnOnConsecutiveCalls(...$responses);

        $stack = new TestMiddlewareStack($compiledPipeline);
        $kernel = new Kernel($finalHandler, $stack);

        foreach ($requests as $index => $request) {
            $result = $kernel->handle($request);
            $this->assertSame($responses[$index], $result);
        }
    }

    protected function createMockRequest(string $method, string $uriString): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($uriString);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri); // Returns UriInterface, not string

        return $request;
    }
}
