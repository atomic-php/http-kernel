# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
```bash
composer test                    # Run all PHPUnit tests
vendor/bin/phpunit              # Direct PHPUnit execution
vendor/bin/phpunit tests/KernelTest.php  # Run specific test file
```

### Code Quality
```bash
composer psalm                  # Run static analysis with Psalm
composer cs-check              # Check code style with PHP-CS-Fixer
composer cs-fix                # Fix code style issues
composer analyze               # Run both psalm and cs-check
```

### Benchmarking
```bash
composer benchmark             # Run all performance benchmarks
composer benchmark-kernel      # Run kernel-specific benchmarks
composer benchmark-middleware  # Run middleware-specific benchmarks
php benchmarks/run-benchmarks.php  # Direct benchmark execution
```

## Architecture Overview

This is a high-performance HTTP kernel library built around **compile-time middleware optimization**. The core principle is to compile middleware stacks once at boot time for zero runtime overhead.

### Core Components

1. **Kernel** (`src/Kernel.php`) - Main entry point that compiles middleware pipeline once at construction
2. **MiddlewareStack** (`src/MiddlewareStack.php`) - Manages middleware collection and compilation into optimized pipeline
3. **OptimizedMiddlewareHandler** (`src/OptimizedMiddlewareHandler.php`) - Runtime handler for executing compiled middleware
4. **Performance Decorators**:
   - **PerformanceKernel** (`src/PerformanceKernel.php`) - Measures request processing metrics
   - **CircuitBreakerKernel** (`src/CircuitBreakerKernel.php`) - Implements circuit breaker pattern

### Key Design Patterns

- **Compile-time optimization**: Middleware pipeline is built once at boot, not per request
- **Zero-overhead execution**: Compiled pipeline executes without runtime reflection or middleware resolution
- **PSR compliance**: Full PSR-7, PSR-11, and PSR-15 compatibility
- **Decorator pattern**: Performance and circuit breaker kernels wrap the base kernel
- **Container integration**: Optional PSR-11 container support for dependency injection

### Namespace Structure
- `Atomic\Http\` - All classes are in this namespace
- Tests: `Tests\` namespace in `tests/` directory
- Benchmarks: `Benchmarks\` namespace in `benchmarks/` directory

### Performance Philosophy
This library prioritizes **performance over runtime flexibility**. The middleware stack is compiled once and cached, making it extremely fast but requiring application restart when middleware changes.

### Testing Strategy
- 100% test coverage with PHPUnit
- Tests cover all core components, edge cases, and performance decorators
- Benchmark suite included for performance regression testing