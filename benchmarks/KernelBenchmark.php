<?php

declare(strict_types=1);

namespace Benchmarks;

use Atomic\Http\CircuitBreakerKernel;
use Atomic\Http\Kernel;
use Atomic\Http\MiddlewareStack;
use Atomic\Http\PerformanceKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lightweight middleware for benchmarking (doesn't store execution data)
 */
class BenchmarkMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public function __construct(protected string $name) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Just pass through - no data storage for performance
        return $handler->handle($request);
    }
}

/**
 * Benchmarks for the Kernel class and related functionality
 */
class KernelBenchmark implements BenchmarkInterface
{
    protected ServerRequestInterface $request;

    protected ResponseInterface $response;

    protected RequestHandlerInterface $simpleHandler;

    protected Kernel $kernelNoMiddleware;

    protected Kernel $kernelWithMiddleware;

    protected CircuitBreakerKernel $circuitBreakerKernel;

    protected PerformanceKernel $performanceKernel;

    public function setUp(): void
    {
        // Create mock request and response
        $this->request = $this->createMockRequest();
        $this->response = $this->createMockResponse();

        // Create simple handler
        $this->simpleHandler = new class($this->response) implements RequestHandlerInterface
        {
            public function __construct(protected ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        // Create kernel with no middleware
        $emptyStack = new MiddlewareStack;
        $this->kernelNoMiddleware = new Kernel($this->simpleHandler, $emptyStack);

        // Create kernel with middleware
        $middlewareStack = new MiddlewareStack;
        $middlewareStack->add(new BenchmarkMiddleware('middleware1'));
        $middlewareStack->add(new BenchmarkMiddleware('middleware2'));
        $middlewareStack->add(new BenchmarkMiddleware('middleware3'));
        $this->kernelWithMiddleware = new Kernel($this->simpleHandler, $middlewareStack);

        // Create circuit breaker kernel
        $this->circuitBreakerKernel = new CircuitBreakerKernel($this->kernelNoMiddleware);

        // Create performance kernel (without callback to avoid overhead)
        $this->performanceKernel = new PerformanceKernel($this->kernelNoMiddleware);
    }

    public function tearDown(): void
    {
        // No cleanup needed for benchmark middleware
    }

    /**
     * Benchmark direct handler execution (baseline)
     */
    public function benchDirectHandler(): void
    {
        $this->simpleHandler->handle($this->request);
    }

    /**
     * Benchmark kernel with no middleware
     */
    public function benchKernelNoMiddleware(): void
    {
        $this->kernelNoMiddleware->handle($this->request);
    }

    /**
     * Benchmark kernel with 3 middleware layers
     */
    public function benchKernelWithMiddleware(): void
    {
        $this->kernelWithMiddleware->handle($this->request);
    }

    /**
     * Benchmark circuit breaker kernel
     */
    public function benchCircuitBreakerKernel(): void
    {
        $this->circuitBreakerKernel->handle($this->request);
    }

    /**
     * Benchmark performance kernel
     */
    public function benchPerformanceKernel(): void
    {
        $this->performanceKernel->handle($this->request);
    }

    /**
     * Benchmark kernel compilation (measures setup overhead)
     */
    public function benchKernelCompilation(): void
    {
        $stack = new MiddlewareStack;
        $stack->add(new BenchmarkMiddleware('temp1'));
        $stack->add(new BenchmarkMiddleware('temp2'));

        // This triggers compilation
        new Kernel($this->simpleHandler, $stack);
    }

    protected function createMockRequest(): ServerRequestInterface
    {
        return new class implements ServerRequestInterface
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

            public function getRequestTarget(): string
            {
                return '/';
            }

            public function withRequestTarget(string $requestTarget): static
            {
                return $this;
            }

            public function getMethod(): string
            {
                return 'GET';
            }

            public function withMethod(string $method): static
            {
                return $this;
            }

            public function getUri(): \Psr\Http\Message\UriInterface
            {
                throw new \RuntimeException('Not implemented');
            }

            public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): static
            {
                return $this;
            }

            public function getServerParams(): array
            {
                return [];
            }

            public function getCookieParams(): array
            {
                return [];
            }

            public function withCookieParams(array $cookies): static
            {
                return $this;
            }

            public function getQueryParams(): array
            {
                return [];
            }

            public function withQueryParams(array $query): static
            {
                return $this;
            }

            public function getUploadedFiles(): array
            {
                return [];
            }

            public function withUploadedFiles(array $uploadedFiles): static
            {
                return $this;
            }

            public function getParsedBody()
            {
                return null;
            }

            public function withParsedBody($data): static
            {
                return $this;
            }

            public function getAttributes(): array
            {
                return [];
            }

            public function getAttribute(string $name, $default = null)
            {
                return $default;
            }

            public function withAttribute(string $name, $value): static
            {
                return $this;
            }

            public function withoutAttribute(string $name): static
            {
                return $this;
            }
        };
    }

    protected function createMockResponse(): ResponseInterface
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
}
