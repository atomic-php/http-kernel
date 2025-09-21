<?php

namespace Tests\Stubs;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test double for Kernel since it's final and can't be mocked
 */
class TestKernel implements RequestHandlerInterface
{
    protected ?ResponseInterface $responseToReturn = null;

    protected ?\Throwable $exceptionToThrow = null;

    protected array $handledRequests = [];

    protected int $handleCallCount = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handleCallCount++;
        $this->handledRequests[] = $request;

        if ($this->exceptionToThrow !== null) {
            throw $this->exceptionToThrow;
        }

        if ($this->responseToReturn === null) {
            throw new \RuntimeException('No response configured for TestKernel');
        }

        return $this->responseToReturn;
    }

    public function willReturn(ResponseInterface $response): self
    {
        $this->responseToReturn = $response;

        return $this;
    }

    public function willThrow(\Throwable $exception): self
    {
        $this->exceptionToThrow = $exception;

        return $this;
    }

    public function getHandledRequests(): array
    {
        return $this->handledRequests;
    }

    public function getHandleCallCount(): int
    {
        return $this->handleCallCount;
    }

    public function wasHandleCalled(): bool
    {
        return $this->handleCallCount > 0;
    }

    public function reset(): void
    {
        $this->responseToReturn = null;
        $this->exceptionToThrow = null;
        $this->handledRequests = [];
        $this->handleCallCount = 0;
    }
}
