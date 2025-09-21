<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Benchmarks\BenchmarkRunner;
use Benchmarks\KernelBenchmark;
use Benchmarks\MiddlewareStackBenchmark;

/**
 * Main benchmark runner script
 */
echo "Atomic HTTP Kernel Benchmarks\n";
echo "============================\n\n";

$runner = new BenchmarkRunner;

// Register all benchmarks
$runner->register(new KernelBenchmark);
$runner->register(new MiddlewareStackBenchmark);

// Run benchmarks
$results = $runner->runAll();

// Display results
echo "\nBenchmark Results Summary:\n";
echo "==============================\n";

foreach ($results as $benchmarkName => $benchmarkResults) {
    echo "\n{$benchmarkName}:\n";
    echo str_repeat('-', strlen($benchmarkName) + 4)."\n";

    foreach ($benchmarkResults as $testName => $result) {
        echo sprintf(
            "  %-30s: %8.2f ops/sec (%6.3f ms/op) [%d iterations]\n",
            $testName,
            $result['ops_per_sec'],
            $result['time_per_op'] * 1000,
            $result['iterations']
        );
    }
}

echo "\nBenchmarks completed!\n";
