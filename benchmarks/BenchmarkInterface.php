<?php

declare(strict_types=1);

namespace Benchmarks;

/**
 * Interface for benchmark classes
 */
interface BenchmarkInterface
{
    /**
     * Set up any resources needed for the benchmark
     * This method is called once before all benchmark methods
     */
    public function setUp(): void;

    /**
     * Clean up resources after the benchmark
     * This method is called once after all benchmark methods
     */
    public function tearDown(): void;
}
