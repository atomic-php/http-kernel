<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atomic\Http\CircuitBreakerKernel;
use Atomic\Http\Kernel;
use Atomic\Http\MiddlewareStack;
use Atomic\Http\PerformanceKernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DecoratorsIntegrationTest extends \PHPUnit\Framework\TestCase
{
    public function test_performance_kernel_reports_metrics_e2e(): void
    {
        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/metrics');

        $final = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(201);
            }
        };

        $stack = new MiddlewareStack();
        $kernel = new Kernel($final, $stack);

        $captured = null;
        $perf = new PerformanceKernel($kernel, function (array $m) use (&$captured) {
            $captured = $m;
        });

        $response = $perf->handle($request);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertIsArray($captured);
        $this->assertArrayHasKey('duration_ms', $captured);
        $this->assertArrayHasKey('status', $captured);
        $this->assertArrayHasKey('method', $captured);
    }

    public function test_circuit_breaker_blocks_then_allows_probe_then_closes(): void
    {
        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/cb');

        $final = new class () implements RequestHandlerInterface {
            public int $calls = 0;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new \RuntimeException('boom');
                }

                return new Response(200);
            }
        };

        $stack = new MiddlewareStack();
        $kernel = new Kernel($final, $stack);
        $cb = new CircuitBreakerKernel($kernel, failureThreshold: 1, recoveryTimeout: 0.001);

        // First call fails and opens the circuit
        try {
            $cb->handle($request);
        } catch (\RuntimeException) {
        }

        // Fast-forward time to allow probe
        $this->setProtected($cb, 'lastFailureTime', microtime(true) - 10.0);

        // Second call should probe and succeed, closing the circuit
        $response = $cb->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('closed', $cb->getState());
    }

    protected function setProtected(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
