<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Testing\ExpectsExactMessage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestCase\ExceptionExpectation;
use ReflectionProperty;

/**
 * A test case that takes ExpectsExactMessage in, as an application's base test case does: the tests that extend it call
 * the trait's method from a subclass. It reads what PHPUnit keeps of a test's expectation of an exception, which its
 * runner checks a thrown exception against (ExceptionExpectation::verify()).
 */
abstract class ExactMessageTestCase extends TestCase
{
    use ExpectsExactMessage;

    /** What PHPUnit keeps of the expectations of another test of this class that expects the message exactly. */
    protected function expectationOf(string $message): ExceptionExpectation
    {
        $test = new static('inner');
        $test->expectExceptionMessageExactly($message);

        $expectation = new ReflectionProperty(TestCase::class, 'exceptionExpectation')->getValue($test);
        assert($expectation instanceof ExceptionExpectation);

        return $expectation;
    }
}
