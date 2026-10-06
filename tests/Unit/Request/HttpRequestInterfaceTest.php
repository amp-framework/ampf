<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\HttpRequest;
use ampf\Request\HttpRequestInterface;
use ampf\Testing\TestHttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionParameter;

/**
 * What a controller reaches through the interface alone, whichever request it was given: the files a form uploaded,
 * whether PHP dropped the form for its size, and the taking back of a redirect. A controller that must first ask
 * whether it was given the framework's own class cannot be run with another request.
 */
#[CoversClass(HttpRequest::class)]
final class HttpRequestInterfaceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}> a method, its parameters (type and name) and its return type
     */
    public static function provideMethods(): iterable
    {
        yield 'the files of a form field' => ['getUploadedFiles', 'string $key', 'array'];
        yield 'whether PHP dropped the request for its size' => ['isPostTooLarge', '', 'bool'];
        // Declared as `self`, which Reflection reports as the interface
        yield 'taking back a redirect' => ['dropRedirect', '', HttpRequestInterface::class];
    }

    /**
     * @return iterable<string, array{class-string, string}> each request that ampf makes, with each of the methods
     */
    public static function provideRequestMethods(): iterable
    {
        foreach ([HttpRequest::class, TestHttpRequest::class] as $class) {
            foreach (self::provideMethods() as [$name]) {
                yield $class . '::' . $name . '()' => [$class, $name];
            }
        }
    }

    #[DataProvider('provideMethods')]
    public function testTheInterfaceDeclaresTheMethod(string $name, string $parameters, string $returnType): void
    {
        $interface = new ReflectionClass(HttpRequestInterface::class);

        self::assertTrue($interface->hasMethod($name), 'HttpRequestInterface does not declare ' . $name . '().');

        $method = $interface->getMethod($name);

        self::assertSame(
            $parameters,
            implode(
                ', ',
                array_map(
                    static fn (ReflectionParameter $parameter): string => (string)$parameter->getType() . ' $' . $parameter->getName(),
                    $method->getParameters(),
                ),
            ),
        );
        self::assertSame($returnType, (string)$method->getReturnType());
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideRequestMethods')]
    public function testTheRequestImplementsTheMethodOfTheInterface(string $class, string $name): void
    {
        $request = new ReflectionClass($class);

        self::assertTrue($request->implementsInterface(HttpRequestInterface::class));
        self::assertSame(
            HttpRequestInterface::class,
            $request->getMethod($name)->getPrototype()->getDeclaringClass()->getName(),
            $class . '::' . $name . '() implements no method of the interface.',
        );
    }
}
