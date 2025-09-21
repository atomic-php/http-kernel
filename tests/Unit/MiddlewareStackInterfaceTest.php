<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Http\MiddlewareStack;
use Atomic\Http\MiddlewareStackInterface;
use PHPUnit\Framework\TestCase;

class MiddlewareStackInterfaceTest extends TestCase
{
    public function test_interface_has_correct_methods()
    {
        $reflection = new \ReflectionClass(MiddlewareStackInterface::class);

        $this->assertTrue($reflection->hasMethod('add'));
        $this->assertTrue($reflection->hasMethod('compile'));

        $addMethod = $reflection->getMethod('add');
        $this->assertEquals('add', $addMethod->getName());
        $this->assertEquals(1, $addMethod->getNumberOfParameters());

        $compileMethod = $reflection->getMethod('compile');
        $this->assertEquals('compile', $compileMethod->getName());
        $this->assertEquals(1, $compileMethod->getNumberOfParameters());
    }

    public function test_interface_is_correctly_defined()
    {
        $reflection = new \ReflectionClass(MiddlewareStackInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertCount(2, $reflection->getMethods());
    }

    public function test_add_method_signature()
    {
        $reflection = new \ReflectionClass(MiddlewareStackInterface::class);
        $addMethod = $reflection->getMethod('add');

        $this->assertEquals('add', $addMethod->getName());
        $this->assertTrue($addMethod->isPublic());
        $this->assertCount(1, $addMethod->getParameters());

        $parameter = $addMethod->getParameters()[0];
        $this->assertEquals('middleware', $parameter->getName());
    }

    public function test_compile_method_signature()
    {
        $reflection = new \ReflectionClass(MiddlewareStackInterface::class);
        $compileMethod = $reflection->getMethod('compile');

        $this->assertEquals('compile', $compileMethod->getName());
        $this->assertTrue($compileMethod->isPublic());
        $this->assertCount(1, $compileMethod->getParameters());

        $parameter = $compileMethod->getParameters()[0];
        $this->assertEquals('handler', $parameter->getName());
    }

    public function test_middleware_stack_implements_interface()
    {
        $stack = new MiddlewareStack();
        $this->assertInstanceOf(MiddlewareStackInterface::class, $stack);
    }

    public function test_interface_can_be_implemented()
    {
        $implementation = new class () implements MiddlewareStackInterface {
            public function add(\Psr\Http\Server\MiddlewareInterface|string $middleware): void
            {
                // Test implementation
            }

            public function compile(\Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Server\RequestHandlerInterface
            {
                return $handler;
            }
        };

        $this->assertInstanceOf(MiddlewareStackInterface::class, $implementation);
    }
}
