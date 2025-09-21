# Atomic HTTP Kernel

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)](tests/)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](#testing)

A **blazingly fast**, zero-overhead HTTP kernel for PHP that compiles middleware stacks at boot time for maximum performance. Built with modern PHP features and designed for high-throughput applications.

## Performance

Atomic HTTP Kernel delivers exceptional performance through compile-time optimization:

- **9M+ operations/sec** - Kernel with no middleware
- **3M+ operations/sec** - Kernel with middleware pipeline
- **Zero runtime overhead** - Middleware compilation happens once at boot
- **Minimal memory footprint** - Efficient object reuse and caching

```text
Kernel Benchmark:
benchDirectHandler            : 11,485,262 ops/sec
benchKernelNoMiddleware       :  9,077,442 ops/sec (20% overhead)
benchKernelWithMiddleware     :  3,122,516 ops/sec
benchCircuitBreakerKernel     :  5,384,332 ops/sec
benchPerformanceKernel        :  6,736,531 ops/sec
```

## Features

- **Zero-Overhead Middleware** - Compile middleware stacks once, execute millions of times
- **PSR-7/PSR-15 Compatible** - Full support for PSR HTTP standards
- **Built-in Resilience** - Circuit breaker and performance monitoring decorators
- **Container Integration** - Optional PSR-11 container support for dependency injection
- **100% Test Coverage** - Comprehensive test suite with PHPUnit
- **Built-in Benchmarking** - Performance measurement tools included
- **Type Safe** - Strict types, readonly classes, and modern PHP 8.4+ features

## Installation

```bash
composer require atomic/http-kernel
```

**Requirements:**

- PHP 8.4 or higher
- PSR-7 HTTP Message implementation
- PSR-15 HTTP Server Request Handler interfaces

## Quick Start

### Basic Usage

```php
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atomic\Http\Kernel;
use Atomic\Http\MiddlewareStack;

// Create your final request handler
$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface {
        // Your application logic here
        return new Response(200, [], 'Hello World!');
    }
};

// Build middleware stack
$stack = new MiddlewareStack();
$stack->add(new AuthenticationMiddleware());
$stack->add(new LoggingMiddleware());
$stack->add(new CorsMiddleware());

// Create kernel (compilation happens here - once!)
$kernel = new Kernel($handler, $stack);

// Handle requests (zero overhead!)
$response = $kernel->handle($request);
```

### With Container Integration

```php
use Psr\Container\ContainerInterface;

$container = new YourContainer();
$stack = new MiddlewareStack($container);

// Add middleware by class name - resolved via container
$stack->add(AuthenticationMiddleware::class);
$stack->add(RateLimitingMiddleware::class);

$kernel = new Kernel($handler, $stack);
```

### Performance Monitoring

```php
use Atomic\Http\PerformanceKernel;

$performanceKernel = new PerformanceKernel(
    kernel: $kernel,
    metricsCallback: function (array $metrics): void {
        // $metrics = ['duration_ms' => 1.23, 'status' => 200, 'method' => 'GET']
        $logger->info('Request processed', $metrics);
    }
);

$response = $performanceKernel->handle($request);
```

### Circuit Breaker for Resilience

```php
use Atomic\Http\CircuitBreakerKernel;

$circuitBreaker = new CircuitBreakerKernel(
    kernel: $kernel,
    failureThreshold: 5,      // Open after 5 failures
    recoveryTimeout: 60.0     // Try again after 60 seconds
);

try {
    $response = $circuitBreaker->handle($request);
} catch (RuntimeException $e) {
    // Circuit breaker is open - service unavailable
    $response = new Response(503, [], 'Service temporarily unavailable');
}
```

## Architecture

### Core Components

```text
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│     Kernel      │───▶│ MiddlewareStack  │───▶│ OptimizedMiddleware │
│                 │    │                  │    │      Handler        │
│ Entry Point     │    │ Compilation &    │    │                     │
│ Request Router  │    │ Caching Logic    │    │ Zero-overhead       │
│                 │    │                  │    │ Execution Chain     │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
```

### Compilation Process

1. **Boot Time**: Middleware stack compiles into optimized handler chain
2. **Runtime**: Pre-compiled pipeline executes with zero overhead
3. **Caching**: Compiled pipeline cached until middleware stack changes

```php
// This happens ONCE at boot:
$compiledPipeline = $stack->compile($handler);

// This happens MILLIONS of times at runtime:
$response = $compiledPipeline->handle($request); // ⚡ Zero overhead!
```

## API Reference

### Kernel

The main entry point for HTTP request processing.

```php
final readonly class Kernel implements RequestHandlerInterface
{
    public function __construct(
        RequestHandlerInterface $handler,
        MiddlewareStackInterface $stack
    );

    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

### MiddlewareStack

Manages and compiles middleware into an optimized pipeline.

```php
final class MiddlewareStack implements MiddlewareStackInterface
{
    public function __construct(?ContainerInterface $container = null);

    public function add(MiddlewareInterface|string $middleware): void;

    public function compile(RequestHandlerInterface $handler): RequestHandlerInterface;
}
```

### Performance Decorators

#### PerformanceKernel

Measures and reports request processing metrics.

```php
final readonly class PerformanceKernel implements RequestHandlerInterface
{
    public function __construct(
        RequestHandlerInterface $kernel,
        ?Closure $metricsCallback = null
    );
}
```

#### CircuitBreakerKernel

Provides circuit breaker pattern for resilience.

```php
final class CircuitBreakerKernel implements RequestHandlerInterface
{
    public function __construct(
        RequestHandlerInterface $kernel,
        int $failureThreshold = 5,
        float $recoveryTimeout = 60.0
    );

    public function getState(): string;
    public function getFailureCount(): int;
    public function getLastFailureTime(): ?float;
}
```

## Testing

Run the comprehensive test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suites
vendor/bin/phpunit tests/KernelTest.php
vendor/bin/phpunit tests/MiddlewareStackTest.php
```

### Test Coverage

- **Kernel**: Request handling, compilation, error propagation
- **MiddlewareStack**: Compilation, caching, container integration
- **Decorators**: Performance monitoring, circuit breaker logic
- **Edge Cases**: Error handling, memory management, type safety

```bash
All tests pass with 100% code coverage
```

## Benchmarking

Measure performance with the built-in benchmark suite:

```bash
# Run all benchmarks
composer benchmark

# View detailed performance metrics
php benchmarks/run-benchmarks.php
```

### Benchmark Results

```text
Kernel Benchmark:
benchDirectHandler            : 11,485,262 ops/sec (0.000 ms/op)
benchKernelNoMiddleware       :  9,077,442 ops/sec (0.000 ms/op)
benchKernelWithMiddleware     :  3,122,516 ops/sec (0.000 ms/op)
benchCircuitBreakerKernel     :  5,384,332 ops/sec (0.000 ms/op)
benchPerformanceKernel        :  6,736,531 ops/sec (0.000 ms/op)

Middleware Stack Benchmark:
benchCompileEmptyStack        : 10,326,182 ops/sec (0.000 ms/op)
benchCompileSmallStack        : 10,176,773 ops/sec (0.000 ms/op)
benchCompileMediumStack       : 10,164,141 ops/sec (0.000 ms/op)
benchCompileLargeStack        : 10,398,357 ops/sec (0.000 ms/op)
```

## Code Quality

Maintain code quality with included tools:

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run static analysis
composer psalm

# Run all quality checks
composer qa
```

## Performance Comparison

| Framework/Library   | Operations/sec    | Notes                    |
| ------------------- | ----------------- | ------------------------ |
| **Atomic HTTP Kernel** | **3,000,000+**    | With middleware pipeline |
| ReactPHP            | 500,000-1,000,000 | Async HTTP server        |
| FastRoute           | 100,000-500,000   | URL routing only         |
| Symfony             | 1,000-5,000       | Full-stack framework     |
| Laravel             | 500-2,000         | Full-stack framework     |

*Note: Benchmarks are approximate and depend on hardware, middleware complexity, and application logic. Atomic HTTP Kernel measurements represent operations per second for request handling, not full HTTP requests per second.*

## Design Principles

### 1. **Performance First**

- Compile-time optimization over runtime flexibility
- Zero-overhead abstractions
- Minimal memory allocations

### 2. **Type Safety**

- Strict types throughout
- Readonly classes where immutability is desired
- Modern PHP 8.4+ features

### 3. **PSR Compliance**

- PSR-7: HTTP Message interfaces
- PSR-11: Container interface (optional)
- PSR-15: HTTP Server Request Handlers

### 4. **Architectural Clarity**

- Single responsibility principle
- Dependency inversion
- Clean separation of concerns

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone repository
git clone https://github.com/atomic/http-kernel.git
cd http-kernel

# Install dependencies
composer install

# Run tests
composer test

# Run benchmarks
composer benchmark

# Check code quality
composer qa
```

### Guidelines

- Follow PSR-12 coding standards
- Add tests for new features
- Update benchmarks for performance-critical changes
- Maintain backwards compatibility

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes and version history.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- **Created by:** Thavarshan ([tjthavarshan@gmail.com](mailto:tjthavarshan@gmail.com))
- Built on PSR standards by the [PHP-FIG](https://www.php-fig.org/)
- Inspired by modern HTTP processing patterns
- Performance techniques from the ReactPHP ecosystem

---

Built by Thavarshan for high-performance PHP applications

> "An idiot admires complexity, a genius admires simplicity" - Terry A. Davis, Creator of Temple OS
