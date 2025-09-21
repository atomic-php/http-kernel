<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ArrayContainer implements ContainerInterface
{
    /** @var array<string,mixed> */
    protected array $entries;

    /** @param array<string,mixed> $entries */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new class("No entry for {$id}") extends \RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
