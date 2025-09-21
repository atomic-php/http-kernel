<?php

declare(strict_types=1);

namespace Atomic\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CircuitBreakerKernel wraps a Kernel and adds circuit breaker logic for resilience.
 * It tracks failures and temporarily blocks requests if too many failures occur,
 * automatically recovering after a timeout.
 *
 * @psalm-api
 */
final class CircuitBreakerKernel implements RequestHandlerInterface
{
    /**
     * Number of consecutive failures.
     * Incremented on every thrown exception while the circuit is closed or half-open.
     */
    protected int $failures = 0;

    /**
     * Timestamp of the last failure in seconds since epoch (microtime(true)).
     * Used to determine when to transition from open -> half_open for a probe.
     */
    protected ?float $lastFailureTime = null;

    /**
     * Circuit state: 'closed' | 'open' | 'half_open'.
     *
     * - closed: normal operation
     * - open: reject immediately until timeout elapses
     * - half_open: allow a probe; success closes, failure re-opens
     */
    protected string $state = 'closed';

    /**
     * Constructs the CircuitBreakerKernel with a kernel and configuration.
     *
     * @param  RequestHandlerInterface  $kernel  The underlying kernel to delegate requests to
     * @param  int  $failureThreshold  Number of failures before opening the circuit
     * @param  float  $recoveryTimeout  Time in seconds before attempting recovery
     */
    public function __construct(
        protected RequestHandlerInterface $kernel,
        protected int $failureThreshold = 5,
        protected float $recoveryTimeout = 60.0, // seconds
    ) {
        //
    }

    /**
     * Handles an incoming HTTP request, applying circuit breaker logic.
     * If the circuit is open, throws an exception. Otherwise, delegates to the kernel.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the kernel
     *
     * @throws \RuntimeException If the circuit breaker is open (HTTP 503 semantics)
     * @throws \Throwable If the underlying kernel throws an exception
     */
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Block request if circuit is open (unless transitioning to half-open)
        if ($this->isCircuitBlocking()) {
            throw new \RuntimeException('Circuit breaker is open', 503);
        }

        try {
            $response = $this->kernel->handle($request);
            $this->onSuccess();

            return $response;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * Expose current circuit state for observability: 'closed' | 'open' | 'half_open'.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Expose current consecutive failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failures;
    }

    /**
     * Expose timestamp of the last failure in seconds since epoch, if any.
     */
    public function getLastFailureTime(): ?float
    {
        return $this->lastFailureTime;
    }

    /**
     * Checks if the circuit is open (blocking requests).
     * If the recovery timeout has passed, resets the circuit.
     *
     * @return bool True if circuit is open, false otherwise
     */
    /**
     * Determine whether the circuit should block the incoming request.
     *
     * - If state is open and timeout not elapsed: block.
     * - If state is open and timeout elapsed: move to half_open and allow a probe.
     * - Otherwise: do not block.
     */
    protected function isCircuitBlocking(): bool
    {
        if ($this->state === 'open') {
            // If enough time has passed, move to half-open to allow a probe
            if ($this->lastFailureTime !== null && (microtime(true) - $this->lastFailureTime) > $this->recoveryTimeout) {
                $this->state = 'half_open';
                // allow request to pass through as a probe
                return false;
            }

            return true; // still open, block
        }

        // In half-open or closed, do not block
        return false;
    }

    /**
     * Called on successful request; resets failure count and closes circuit.
     */
    protected function onSuccess(): void
    {
        $this->failures = 0;
        // Success during half-open closes the circuit fully
        $this->state = 'closed';
    }

    /**
     * Called on failed request; increments failure count and opens circuit if needed.
     */
    protected function onFailure(): void
    {
        $this->failures++;
        $this->lastFailureTime = microtime(true);

        if ($this->state === 'half_open') {
            // Probe failed, immediately re-open
            $this->state = 'open';
            return;
        }

        // In closed state: open when threshold reached
        if ($this->state === 'closed' && $this->failures >= $this->failureThreshold) {
            $this->state = 'open';
        }
    }
}
