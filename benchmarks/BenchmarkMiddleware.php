<?php

declare(strict_types=1);

namespace Benchmarks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lightweight middleware for benchmarking (doesn't store execution data)
 */
class BenchmarkMiddleware implements MiddlewareInterface
{
    public function __construct(protected string $name) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Just pass through - no data storage for performance
        return $handler->handle($request);
    }
}
