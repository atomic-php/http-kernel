<?php

declare(strict_types=1);

namespace Benchmarks;

use ReflectionClass;
use ReflectionMethod;

/**
 * Simple benchmark runner that executes benchmark methods and measures performance
 */
class BenchmarkRunner
{
    /**
     * @var BenchmarkInterface[]
     */
    protected array $benchmarks = [];

    /**
     * Register a benchmark class
     */
    public function register(BenchmarkInterface $benchmark): void
    {
        $this->benchmarks[] = $benchmark;
    }

    /**
     * Run all registered benchmarks
     *
     * @return array<string, array<string, array{iterations: int, total_time: float, time_per_op: float, ops_per_sec: float}>>
     */
    public function runAll(): array
    {
        $results = [];

        foreach ($this->benchmarks as $benchmark) {
            $benchmarkName = $this->getBenchmarkName($benchmark);
            echo "Running {$benchmarkName}...\n";

            $results[$benchmarkName] = $this->runBenchmark($benchmark);
        }

        return $results;
    }

    /**
     * Run a single benchmark and return results
     *
     * @return array<string, array{iterations: int, total_time: float, time_per_op: float, ops_per_sec: float}>
     */
    protected function runBenchmark(BenchmarkInterface $benchmark): array
    {
        $reflection = new ReflectionClass($benchmark);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $results = [];

        // Setup benchmark
        if (method_exists($benchmark, 'setUp')) {
            $benchmark->setUp();
        }

        foreach ($methods as $method) {
            $methodName = $method->getName();

            // Skip non-benchmark methods
            if (! str_starts_with($methodName, 'bench') || $methodName === 'setUp' || $methodName === 'tearDown') {
                continue;
            }

            echo "  {$methodName}... ";

            $results[$methodName] = $this->measureMethod($benchmark, $methodName);

            echo sprintf(
                "%8.2f ops/sec\n",
                $results[$methodName]['ops_per_sec']
            );
        }

        // Teardown benchmark
        if (method_exists($benchmark, 'tearDown')) {
            $benchmark->tearDown();
        }

        return $results;
    }

    /**
     * Measure the performance of a specific method
     *
     * @return array{iterations: int, total_time: float, time_per_op: float, ops_per_sec: float}
     */
    protected function measureMethod(BenchmarkInterface $benchmark, string $methodName): array
    {
        $iterations = 0;
        $totalTime = 0.0;
        $targetTime = 1.0; // Run for 1 second
        $minIterations = 100;

        // Warm-up run
        $benchmark->$methodName();

        $startTime = hrtime(true);

        do {
            $benchmark->$methodName();
            $iterations++;

            $currentTime = hrtime(true);
            $totalTime = ($currentTime - $startTime) / 1_000_000_000; // Convert to seconds

        } while ($totalTime < $targetTime || $iterations < $minIterations);

        $timePerOp = $totalTime / $iterations;
        $opsPerSec = 1.0 / $timePerOp;

        return [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'time_per_op' => $timePerOp,
            'ops_per_sec' => $opsPerSec,
        ];
    }

    /**
     * Get a human-readable name for the benchmark
     */
    protected function getBenchmarkName(BenchmarkInterface $benchmark): string
    {
        $className = get_class($benchmark);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        // Convert CamelCase to readable format
        return preg_replace('/([A-Z])/', ' $1', $shortName);
    }
}
