<?php

declare(strict_types=1);

namespace Atomic\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PerformanceKernel wraps a Kernel and records performance metrics for each request.
 * If a metrics callback is provided, it is called with timing and status information.
 *
 * @psalm-api
 */
final readonly class PerformanceKernel implements RequestHandlerInterface
{
    /**
     * Constructs the PerformanceKernel with a kernel and an optional metrics callback.
     *
     * @param  RequestHandlerInterface  $kernel  The underlying kernel to handle requests
     * @param  Closure|null  $metricsCallback  Optional callback to record metrics
     *
     * @psalm-param null|Closure(array{duration_ms: float, status: int, method: string, exception_class?: class-string<\Throwable>}):void $metricsCallback
     *               Callback receives duration/status/method, with optional exception_class on failures.
     */
    public function __construct(
        protected RequestHandlerInterface $kernel,
        protected ?Closure $metricsCallback = null,
    ) {
        //
    }

    /**
     * Handles an HTTP request and records performance metrics if a callback is set.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the kernel
     *
     * On success, reports duration, status code, and method.
     * On exception, reports duration, method, and exception class; rethrows the exception.
     */
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // If no metrics callback, just delegate to the kernel
        if (! $this->metricsCallback) {
            return $this->kernel->handle($request);
        }

        $start = hrtime(true);
        try {
            $response = $this->kernel->handle($request);
            $duration = (hrtime(true) - $start) / 1_000_000;
            ($this->metricsCallback)([
                'duration_ms' => $duration,
                'status' => $response->getStatusCode(),
                'method' => $request->getMethod(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            $duration = (hrtime(true) - $start) / 1_000_000;
            ($this->metricsCallback)([
                'duration_ms' => $duration,
                'status' => 0,
                'method' => $request->getMethod(),
                'exception_class' => $e::class,
            ]);
            throw $e;
        }
    }
}
