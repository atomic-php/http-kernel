<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test middleware for tracking execution order and behavior
 */
class TestMiddleware implements MiddlewareInterface
{
    /**
     * Track the order in which middleware instances are executed
     */
    public static array $executionOrder = [];

    /**
     * Track all calls made to middleware
     */
    public static array $calls = [];

    public function __construct(
        protected string $name = 'test'
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        self::$executionOrder[] = $this->name;
        self::$calls[] = [
            'middleware' => $this->name,
            'request' => $request,
            'handler' => $handler,
        ];

        return $handler->handle($request);
    }

    /**
     * Reset static tracking data between tests
     */
    public static function reset(): void
    {
        self::$executionOrder = [];
        self::$calls = [];
    }

    /**
     * Get the name of this middleware instance
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the number of times any middleware has been executed
     */
    public static function getExecutionCount(): int
    {
        return count(self::$executionOrder);
    }

    /**
     * Check if a specific middleware name was executed
     */
    public static function wasExecuted(string $name): bool
    {
        return in_array($name, self::$executionOrder, true);
    }

    /**
     * Get the order index of a middleware execution (0-based)
     */
    public static function getExecutionIndex(string $name): ?int
    {
        $index = array_search($name, self::$executionOrder, true);

        return $index !== false ? $index : null;
    }
}
