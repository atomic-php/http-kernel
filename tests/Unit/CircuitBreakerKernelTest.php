<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__.'/../Stubs/TestKernel.php';

use Atomic\Http\CircuitBreakerKernel;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Stubs\TestKernel;

class CircuitBreakerKernelTest extends TestCase
{
    public function test_opens_after_threshold_and_blocks(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $kernel = new TestKernel();
        $kernel->willThrow(new \RuntimeException('Downstream failure'));

        $cb = new CircuitBreakerKernel($kernel, failureThreshold: 2, recoveryTimeout: 60.0);

        // Two consecutive failures reach threshold
        try {
            $cb->handle($request);
        } catch (\Throwable $ignored) {
        }
        try {
            $cb->handle($request);
        } catch (\Throwable $ignored) {
        }

        $callsAfterOpen = $kernel->getHandleCallCount();

        // Third call should be blocked by circuit (not delegated)
        try {
            $cb->handle($request);
            $this->fail('Expected open-circuit exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('Circuit breaker is open', $e->getMessage());
        }

        $this->assertSame($callsAfterOpen, $kernel->getHandleCallCount(), 'Kernel should not be called when open');
    }

    public function test_allows_probe_after_timeout_and_closes_on_success(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $kernel = new TestKernel();

        $cb = new CircuitBreakerKernel($kernel, failureThreshold: 1, recoveryTimeout: 0.001);

        // Simulate an already-open circuit whose timeout elapsed
        $this->setProtected($cb, 'state', 'open');
        $this->setProtected($cb, 'lastFailureTime', microtime(true) - 10.0);

        // Next request should probe (half-open) and succeed -> closes circuit
        $kernel->willReturn($response);
        $result = $cb->handle($request);
        $this->assertSame($response, $result);

        // Subsequent request should pass normally (closed)
        $result2 = $cb->handle($request);
        $this->assertSame($response, $result2);
    }

    public function test_probe_failure_reopens_and_blocks(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $kernel = new TestKernel();

        $cb = new CircuitBreakerKernel($kernel, failureThreshold: 1, recoveryTimeout: 0.001);

        // Open circuit
        $kernel->willThrow(new \RuntimeException('Fail'));
        try {
            $cb->handle($request);
        } catch (\Throwable $ignored) {
        }

        // Force timeout to allow half-open probe
        $this->setProtected($cb, 'lastFailureTime', microtime(true) - 10.0);
        $this->setProtected($cb, 'state', 'open');

        $preProbeCalls = $kernel->getHandleCallCount();

        // Probe fails -> circuit re-opens
        $kernel->willThrow(new \RuntimeException('Still failing'));
        try {
            $cb->handle($request);
        } catch (\Throwable $ignored) {
        }

        // Next call should be blocked without delegating
        try {
            $cb->handle($request);
            $this->fail('Expected open-circuit exception after failed probe');
        } catch (\RuntimeException $e) {
            $this->assertSame('Circuit breaker is open', $e->getMessage());
        }

        $this->assertSame($preProbeCalls + 1, $kernel->getHandleCallCount(), 'Only the probe should have reached kernel');
    }

    protected function setProtected(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
