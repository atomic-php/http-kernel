<?php

declare(strict_types=1);

namespace Atomic\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MiddlewareStack manages a stack of middleware for HTTP request processing.
 * It supports adding middleware, compiling the stack into a pipeline, and caching
 * middleware instances for performance. Compilation is done once at boot.
 *
 * @psalm-api
 */
final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * List of middleware (instances or class names).
     *
     * @var array<MiddlewareInterface|string>
     */
    protected array $middleware = [];

    /**
     * Cache of resolved middleware instances by identifier (typically class name).
     * Avoids repeated container lookups and constructions during compilation.
     *
     * @var array<string, MiddlewareInterface>
     */
    protected array $instanceCache = [];

    /**
     * Cached compiled middleware pipelines per final handler.
     *
     * @var array<int, RequestHandlerInterface> keyed by spl_object_id($handler)
     */
    protected array $compiledPipelines = [];

    /**
     * Constructs the MiddlewareStack with an optional container for resolving middleware.
     *
     * @param  ContainerInterface|null  $container  Optional container for resolving middleware
     */
    public function __construct(
        protected ?ContainerInterface $container = null,
    ) {
        //
    }

    /**
     * Adds a middleware to the stack and invalidates the compiled pipeline cache.
     *
     * @param  MiddlewareInterface|string  $middleware  The middleware instance or class name
     */
    #[\Override]
    public function add(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
        // Invalidate all compiled pipelines when middleware composition changes
        $this->compiledPipelines = [];
    }

    /**
     * Compiles the middleware stack into an optimized handler pipeline.
     * This is called once at boot for maximum performance.
     *
     * @param  RequestHandlerInterface  $handler  The final handler
     * @return RequestHandlerInterface The compiled pipeline
     */
    #[\Override]
    public function compile(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        // Return cached pipeline for this handler if already compiled
        $key = \spl_object_id($handler);
        if (isset($this->compiledPipelines[$key])) {
            return $this->compiledPipelines[$key];
        }
        // If no middleware, cache and return the handler directly
        if (empty($this->middleware)) {
            return $this->compiledPipelines[$key] = $handler;
        }
        // Resolve all middleware instances in reverse order (outermost first)
        $resolvedMiddleware = [];
        foreach (array_reverse($this->middleware) as $middleware) {
            $resolvedMiddleware[] = $this->resolveMiddleware($middleware);
        }
        // Build the pipeline by wrapping each middleware around the handler
        $pipeline = $handler;
        foreach ($resolvedMiddleware as $middleware) {
            $pipeline = new OptimizedMiddlewareHandler($middleware, $pipeline);
        }

        // Cache and return the compiled pipeline
        return $this->compiledPipelines[$key] = $pipeline;
    }

    /**
     * Clear all compiled pipelines. Use when changing the final handler topology at runtime.
     * Does not clear the middleware instance cache.
     */
    public function clearCompiledPipelines(): void
    {
        $this->compiledPipelines = [];
    }

    /**
     * Resolves a middleware instance, using the cache if available.
     * If a class name is given, it is resolved via the container or instantiated directly.
     *
     * @param  MiddlewareInterface|string  $middleware  The middleware instance or class name
     * @return MiddlewareInterface The resolved middleware instance
     */
    /**
     * Resolve a middleware specification to a concrete MiddlewareInterface instance.
     *
     * @param  MiddlewareInterface|string  $middleware  Instance or class-string to resolve
     * @return MiddlewareInterface
     *
     * @throws \InvalidArgumentException If the value cannot be resolved to MiddlewareInterface
     */
    protected function resolveMiddleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        // If already an instance, return it
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        // By this point, $middleware must be a class-string
        $class = $middleware; // narrow to string for static analysis

        // Return cached instance if available
        if (isset($this->instanceCache[$class])) {
            return $this->instanceCache[$class];
        }

        // Resolve via container or instantiate directly
        if ($this->container !== null) {
            // Defer to container resolution; implementations typically throw on unknown id
            $instance = $this->container->get($class);
        } else {
            if (! \class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Middleware class "%s" not found', $class));
            }
            $instance = new $class();
        }

        if (! $instance instanceof MiddlewareInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Middleware "%s" must resolve to an instance of %s',
                    $class,
                    MiddlewareInterface::class
                )
            );
        }

        // Cache and return
        return $this->instanceCache[$class] = $instance;
    }
}
