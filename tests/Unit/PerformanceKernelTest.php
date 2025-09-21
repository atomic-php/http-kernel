<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../Stubs/TestKernel.php';

use Atomic\Http\PerformanceKernel;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Stubs\TestKernel;

class PerformanceKernelTest extends TestCase
{
    public function test_handle_delegates_without_callback(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $kernel = new TestKernel();
        $kernel->willReturn($response);

        $performanceKernel = new PerformanceKernel($kernel);
        $result = $performanceKernel->handle($request);

        $this->assertSame($response, $result);
        $this->assertTrue($kernel->wasHandleCalled());
        $this->assertEquals(1, $kernel->getHandleCallCount());
        $this->assertSame([$request], $kernel->getHandledRequests());
    }

    public function test_handle_calls_metrics_callback(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $kernel = new TestKernel();
        $kernel->willReturn($response);

        $metricsCallbackCalled = false;
        $recordedMetrics = null;

        $metricsCallback = function ($metrics) use (&$metricsCallbackCalled, &$recordedMetrics) {
            $metricsCallbackCalled = true;
            $recordedMetrics = $metrics;
        };

        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);
        $result = $performanceKernel->handle($request);

        $this->assertSame($response, $result);
        $this->assertTrue($kernel->wasHandleCalled());
        $this->assertTrue($metricsCallbackCalled);
        $this->assertIsArray($recordedMetrics);
        $this->assertArrayHasKey('duration_ms', $recordedMetrics);
        $this->assertArrayHasKey('status', $recordedMetrics);
        $this->assertArrayHasKey('method', $recordedMetrics);
        $this->assertGreaterThanOrEqual(0, $recordedMetrics['duration_ms']);
    }

    public function test_handle_propagates_exceptions_with_metrics(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = new \RuntimeException('Test exception');

        $kernel = new TestKernel();
        $kernel->willThrow($exception);

        $metricsCallbackCalled = false;
        $recordedMetrics = null;

        $metricsCallback = function ($metrics) use (&$metricsCallbackCalled, &$recordedMetrics) {
            $metricsCallbackCalled = true;
            $recordedMetrics = $metrics;
        };

        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $performanceKernel->handle($request);
        } finally {
            $this->assertTrue($metricsCallbackCalled);
            $this->assertIsArray($recordedMetrics);
            $this->assertArrayHasKey('duration_ms', $recordedMetrics);
            $this->assertArrayHasKey('method', $recordedMetrics);
            $this->assertArrayHasKey('exception_class', $recordedMetrics);
        }
    }
}
