<?php

declare(strict_types=1);

namespace Benchmarks;

use Atomic\Http\MiddlewareStack;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Benchmarks for the MiddlewareStack class
 */
class MiddlewareStackBenchmark implements BenchmarkInterface
{
    protected RequestHandlerInterface $handler;

    protected MiddlewareStack $emptyStack;

    protected MiddlewareStack $smallStack;

    protected MiddlewareStack $mediumStack;

    protected MiddlewareStack $largeStack;

    protected MiddlewareInterface $middleware;

    public function setUp(): void
    {
        $this->handler = new class implements RequestHandlerInterface
        {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new class implements ResponseInterface
                {
                    public function getProtocolVersion(): string
                    {
                        return '1.1';
                    }

                    public function withProtocolVersion(string $version): static
                    {
                        return $this;
                    }

                    public function getHeaders(): array
                    {
                        return [];
                    }

                    public function hasHeader(string $name): bool
                    {
                        return false;
                    }

                    public function getHeader(string $name): array
                    {
                        return [];
                    }

                    public function getHeaderLine(string $name): string
                    {
                        return '';
                    }

                    public function withHeader(string $name, $value): static
                    {
                        return $this;
                    }

                    public function withAddedHeader(string $name, $value): static
                    {
                        return $this;
                    }

                    public function withoutHeader(string $name): static
                    {
                        return $this;
                    }

                    public function getBody(): \Psr\Http\Message\StreamInterface
                    {
                        throw new \RuntimeException('Not implemented');
                    }

                    public function withBody(\Psr\Http\Message\StreamInterface $body): static
                    {
                        return $this;
                    }

                    public function getStatusCode(): int
                    {
                        return 200;
                    }

                    public function withStatus(int $code, string $reasonPhrase = ''): static
                    {
                        return $this;
                    }

                    public function getReasonPhrase(): string
                    {
                        return 'OK';
                    }
                };
            }
        };

        $this->middleware = new BenchmarkMiddleware('benchmark');

        // Empty stack
        $this->emptyStack = new MiddlewareStack;

        // Small stack (3 middleware)
        $this->smallStack = new MiddlewareStack;
        for ($i = 1; $i <= 3; $i++) {
            $this->smallStack->add(new BenchmarkMiddleware("small_{$i}"));
        }

        // Medium stack (10 middleware)
        $this->mediumStack = new MiddlewareStack;
        for ($i = 1; $i <= 10; $i++) {
            $this->mediumStack->add(new BenchmarkMiddleware("medium_{$i}"));
        }

        // Large stack (50 middleware)
        $this->largeStack = new MiddlewareStack;
        for ($i = 1; $i <= 50; $i++) {
            $this->largeStack->add(new BenchmarkMiddleware("large_{$i}"));
        }
    }

    public function tearDown(): void
    {
        // No cleanup needed for benchmark middleware
    }

    /**
     * Benchmark compiling an empty stack
     */
    public function benchCompileEmptyStack(): void
    {
        $this->emptyStack->compile($this->handler);
    }

    /**
     * Benchmark compiling a small stack (3 middleware)
     */
    public function benchCompileSmallStack(): void
    {
        $this->smallStack->compile($this->handler);
    }

    /**
     * Benchmark compiling a medium stack (10 middleware)
     */
    public function benchCompileMediumStack(): void
    {
        $this->mediumStack->compile($this->handler);
    }

    /**
     * Benchmark compiling a large stack (50 middleware)
     */
    public function benchCompileLargeStack(): void
    {
        $this->largeStack->compile($this->handler);
    }

    /**
     * Benchmark adding middleware to stack
     */
    public function benchAddMiddleware(): void
    {
        $stack = new MiddlewareStack;
        $stack->add($this->middleware);
    }

    /**
     * Benchmark adding middleware by class name
     */
    public function benchAddMiddlewareByClassName(): void
    {
        $stack = new MiddlewareStack;
        $stack->add(BenchmarkMiddleware::class);
    }

    /**
     * Benchmark stack compilation caching
     */
    public function benchCompilationCaching(): void
    {
        // First compilation triggers actual work
        $compiled1 = $this->smallStack->compile($this->handler);

        // Second compilation should use cache
        $compiled2 = $this->smallStack->compile($this->handler);
    }

    /**
     * Benchmark building a stack incrementally
     */
    public function benchIncrementalStackBuilding(): void
    {
        $stack = new MiddlewareStack;

        // Add 5 middleware incrementally
        for ($i = 1; $i <= 5; $i++) {
            $stack->add(new BenchmarkMiddleware("incremental_{$i}"));
        }

        // Compile the final stack
        $stack->compile($this->handler);
    }
}
