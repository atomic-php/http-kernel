<?php

namespace Tests\Stubs;

use Atomic\Http\MiddlewareStackInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test implementation of MiddlewareStackInterface since we can't mock final classes
 */
class TestMiddlewareStack implements MiddlewareStackInterface
{
    protected RequestHandlerInterface $compiledHandler;

    protected int $compileCallCount = 0;

    protected bool $compileCalled = false;

    public function __construct(RequestHandlerInterface $compiledHandler)
    {
        $this->compiledHandler = $compiledHandler;
    }

    public function add(string|MiddlewareInterface $middleware): void
    {
        // Test implementation - no-op since we're just testing the kernel
    }

    public function compile(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        $this->compileCallCount++;
        $this->compileCalled = true;

        return $this->compiledHandler;
    }

    public function wasCompileCalled(): bool
    {
        return $this->compileCalled;
    }

    public function getCompileCallCount(): int
    {
        return $this->compileCallCount;
    }
}
