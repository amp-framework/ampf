<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Hasher;

use ampf\Service\Hasher\HasherService;
use ampf\Service\Hasher\HasherServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use SensitiveParameter;

/**
 * The strings the hasher is handed are passwords: marked `#[SensitiveParameter]`, a stack trace shows an object in
 * their place whatever the php.ini says about the arguments of a trace, so that what was typed is never written to a
 * log by a failure that happens below the call. PHP looks at the function that runs, so the interface's attribute is no
 * protection: the class marks its own parameters, and an implementation of an application's does the same.
 */
#[CoversClass(HasherService::class)]
final class SensitiveParametersTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, string}> each parameter that holds a password
     */
    public static function provideSecrets(): iterable
    {
        foreach ([HasherServiceInterface::class, HasherService::class] as $type) {
            yield $type . '::avoidTimingAttack()' => [$type, 'avoidTimingAttack', 'input'];
            yield $type . '::check()' => [$type, 'check', 'string'];
            yield $type . '::hash()' => [$type, 'hash', 'string'];
        }

        yield HasherService::class . '::verify()' => [HasherService::class, 'verify', 'string'];
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('provideSecrets')]
    public function testAPasswordIsASensitiveParameter(string $type, string $method, string $name): void
    {
        $parameters = array_filter(
            new ReflectionMethod($type, $method)->getParameters(),
            static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name,
        );

        self::assertCount(1, $parameters, $type . '::' . $method . '() has no parameter $' . $name . '.');
        self::assertCount(1, current($parameters)->getAttributes(SensitiveParameter::class));
    }

    public function testNoOtherParameterIsMarked(): void
    {
        $marked = [];

        foreach ([HasherServiceInterface::class, HasherService::class] as $type) {
            foreach (new ReflectionMethod($type, 'check')->getParameters() as $parameter) {
                if ($parameter->getAttributes(SensitiveParameter::class) !== []) {
                    $marked[] = $type . '::check($' . $parameter->getName() . ')';
                }
            }
        }

        self::assertSame(
            [HasherServiceInterface::class . '::check($string)', HasherService::class . '::check($string)'],
            $marked,
            'The stored hash is not the secret.',
        );
    }
}
