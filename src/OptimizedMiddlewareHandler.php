<?php

declare(strict_types=1);

namespace Atomic\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * OptimizedMiddlewareHandler wraps a single middleware and the next handler in the pipeline.
 * It is used to build a pre-compiled, zero-overhead middleware chain.
 *
 * @psalm-api
 */
final readonly class OptimizedMiddlewareHandler implements RequestHandlerInterface
{
    /**
     * Constructs the OptimizedMiddlewareHandler with a middleware and the next handler.
     *
     * @param  MiddlewareInterface  $middleware  The middleware to invoke
     * @param  RequestHandlerInterface  $next  The next handler in the pipeline
     */
    public function __construct(
        protected MiddlewareInterface $middleware,
        protected RequestHandlerInterface $next,
    ) {
        //
    }

    /**
     * Handles an HTTP request by invoking the middleware and passing control to the next handler.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the middleware
     *
     * Equivalent to: return $middleware->process($request, $next);
     */
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Directly call the middleware's process method with the next handler
        return $this->middleware->process($request, $this->next);
    }
}
