<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Bean;

use ampf\Bean\BeanFactory;
use ampf\Tests\Support\Bean\AbstractBean;
use ampf\Tests\Support\Bean\PlainBean;
use ampf\Tests\Support\Bean\RecordingBean;
use ampf\Tests\Support\Bean\SeamBeanFactory;
use ArrayObject;
use Countable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(BeanFactory::class)]
final class BeanFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideMalformedDefinitions(): iterable
    {
        yield 'no array' => [RecordingBean::class, 'The bean bean must be defined by an array, not string.'];
        yield 'an unknown option' => [
            ['class' => RecordingBean::class, 'initmethod' => 'init'],
            'The bean bean has the unknown option initmethod (known are class, scope, properties, initMethod, parent).',
        ];
        yield 'a list' => [[RecordingBean::class], 'The bean bean has the unknown option 0 (known are class, scope, properties, initMethod, parent).'];
        yield 'no class' => [['scope' => 'prototype'], 'The bean bean names no class that exists.'];
        yield 'a class of another type' => [['class' => 42], 'The bean bean names no class that exists.'];
        yield 'a class that does not exist' => [['class' => 'ampf\Tests\Missing'], 'The bean bean names no class that exists.'];
        yield 'an abstract class' => [
            ['class' => AbstractBean::class],
            'The bean bean names the class ' . AbstractBean::class . ', which cannot be instantiated.',
        ];
        yield 'an interface' => [
            ['class' => Countable::class],
            'The bean bean names no class that exists.',
        ];
        yield 'an unknown scope' => [
            ['class' => RecordingBean::class, 'scope' => 'request'],
            'The bean bean has a scope other than singleton and prototype.',
        ];
        yield 'a scope of another type' => [
            ['class' => RecordingBean::class, 'scope' => true],
            'The bean bean has a scope other than singleton and prototype.',
        ];
        yield 'properties of another type' => [
            ['class' => RecordingBean::class, 'properties' => 'Config'],
            'The bean bean must list its properties in an array.',
        ];
        yield 'a property without its bean id' => [
            ['class' => RecordingBean::class, 'properties' => ['dependency']],
            'The bean bean must list its properties as bean id => setter suffix.',
        ];
        yield 'a property without its setter' => [
            ['class' => RecordingBean::class, 'properties' => ['Config' => null]],
            'The bean bean must list its properties as bean id => setter suffix.',
        ];
        yield 'a property with an empty setter' => [
            ['class' => RecordingBean::class, 'properties' => ['Config' => '']],
            'The bean bean must list its properties as bean id => setter suffix.',
        ];
        yield 'an init method of another type' => [
            ['class' => RecordingBean::class, 'initMethod' => ['init']],
            'The bean bean must name its initMethod by a string.',
        ];
        yield 'an empty init method' => [
            ['class' => RecordingBean::class, 'initMethod' => ''],
            'The bean bean must name its initMethod by a string.',
        ];
        yield 'a parent of another type' => [
            ['class' => RecordingBean::class, 'parent' => 7],
            'The bean bean must name its parent by a string.',
        ];
    }

    public function testTheFactoryAndTheConfigurationAreBeans(): void
    {
        $factory = new BeanFactory(['beans' => [], 'routes' => []]);

        self::assertSame($factory, $factory->get('BeanFactory'));
        self::assertSame(['beans' => [], 'routes' => []], $factory->get('Config'));
        self::assertSame(['beans' => [], 'routes' => []], $factory->getConfig());
        self::assertTrue($factory->has('BeanFactory'));
        self::assertTrue($factory->has('Config'));
    }

    public function testASubclassChangesTheStepsOfTheCreation(): void
    {
        $factory = new SeamBeanFactory(['beans' => [
            'base' => ['class' => PlainBean::class],
            'bean' => ['class' => RecordingBean::class, 'parent' => 'base'],
        ]]);

        $factory->get('bean');

        self::assertSame(
            ['getDefinition bean', 'getDefinitions', 'configure bean', 'getDefinition base', 'getDefinitions', 'configure base'],
            $factory->getCalls(),
        );
    }

    public function testASingletonIsCreatedOnce(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => PlainBean::class]]]);

        $bean = $factory->get('bean');

        self::assertInstanceOf(PlainBean::class, $bean);
        self::assertSame($bean, $factory->get('bean'));
        self::assertSame(['beansCreated' => 1], $factory->getStatistics());
    }

    public function testTheSingletonScopeMayBeNamed(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => PlainBean::class, 'scope' => 'singleton']]]);

        self::assertSame($factory->get('bean'), $factory->get('bean'));
    }

    public function testAPrototypeIsCreatedAtEveryLookup(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => PlainBean::class, 'scope' => 'prototype']]]);

        $first = $factory->get('bean');

        self::assertInstanceOf(PlainBean::class, $first);
        self::assertNotSame($first, $factory->get('bean'));
        self::assertSame(['beansCreated' => 2], $factory->getStatistics());
    }

    public function testABeanSetByHandWinsOverItsConfiguration(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => PlainBean::class]]]);
        $double = new stdClass();

        self::assertSame($factory, $factory->set('bean', $double));
        self::assertSame($double, $factory->get('bean'));
        self::assertSame(['beansCreated' => 0], $factory->getStatistics());
    }

    public function testABeanIsKnownWhenItWasSetOrIsConfigured(): void
    {
        $factory = new BeanFactory(['beans' => ['configured' => ['class' => PlainBean::class], 'empty' => null]]);
        $factory->set('set', new stdClass());
        $factory->set('null', null);

        self::assertTrue($factory->has('configured'));
        self::assertTrue($factory->has('set'));
        self::assertFalse($factory->has('unknown'));
        self::assertFalse($factory->has('null'), 'a bean set to null is none');
        self::assertFalse($factory->has('empty'), 'a definition of null is none');
    }

    public function testAnUnknownBeanIsRefused(): void
    {
        $factory = new BeanFactory([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No configuration for bean unknown found.');

        $factory->get('unknown');
    }

    public function testACreatorMakesABeanWithoutAConfiguration(): void
    {
        $factory = new BeanFactory([]);
        /** @var ArrayObject<int, array{BeanFactory, array<mixed>}> $calls */
        $calls = new ArrayObject();
        $creator = static function (BeanFactory $beanFactory, array $definition) use ($calls): RecordingBean {
            $calls->append([$beanFactory, $definition]);

            return new RecordingBean();
        };

        $bean = $factory->get('created', $creator);

        self::assertInstanceOf(RecordingBean::class, $bean);
        self::assertSame($factory, $bean->getBeanFactory(), 'the factory is handed over');
        self::assertSame($bean, $factory->get('created', $creator), 'kept as a singleton');
        self::assertSame(
            [[$factory, []]],
            $calls->getArrayCopy(),
            'the creator ran once, with the factory and no configuration',
        );
        self::assertTrue($factory->has('created'));
    }

    public function testAConfiguredBeanWinsOverTheCreator(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => PlainBean::class]]]);

        self::assertInstanceOf(PlainBean::class, $factory->get('bean', static fn (): stdClass => new stdClass()));
    }

    public function testACreatorMayMakeSomethingElseThanAnObject(): void
    {
        $factory = new BeanFactory([]);

        self::assertSame(['a', 'list'], $factory->get('list', static fn (): array => ['a', 'list']));
        self::assertSame(['a', 'list'], $factory->get('list'));
    }

    public function testThePropertiesTheFactoryAndTheInitMethodComeInThatOrder(): void
    {
        $dependency = new stdClass();
        $factory = new BeanFactory(['beans' => [
            'bean' => [
                'class' => RecordingBean::class,
                'properties' => ['dependency' => 'dependency', 'Config' => 'other'],
                'initMethod' => 'init',
            ],
        ]]);
        $factory->set('dependency', $dependency);

        $bean = $factory->get('bean');

        self::assertInstanceOf(RecordingBean::class, $bean);
        self::assertSame(['setDependency', 'setOther', 'setBeanFactory', 'init'], $bean->getCalls());
        self::assertSame($dependency, $bean->getDependency());
        self::assertSame($factory, $bean->getBeanFactory());
    }

    public function testADependencyIsCreatedForItsProperty(): void
    {
        $factory = new BeanFactory(['beans' => [
            'bean' => ['class' => RecordingBean::class, 'properties' => ['plain' => 'dependency']],
            'plain' => ['class' => PlainBean::class],
        ]]);

        $bean = $factory->get('bean');

        self::assertInstanceOf(RecordingBean::class, $bean);
        self::assertSame($factory->get('plain'), $bean->getDependency());
    }

    public function testAPropertyWithoutItsSetterIsRefused(): void
    {
        $factory = new BeanFactory(
            ['beans' => ['bean' => ['class' => PlainBean::class, 'properties' => ['Config' => 'config']]]],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The bean bean cannot take the bean Config: ' . PlainBean::class . ' has no method setConfig().',
        );

        $factory->get('bean');
    }

    public function testAnInitMethodThatIsNoPublicMethodIsRefused(): void
    {
        foreach (['missing', 'hidden'] as $initMethod) {
            $factory = new BeanFactory(
                ['beans' => ['bean' => ['class' => RecordingBean::class, 'initMethod' => $initMethod]]],
            );

            try {
                $factory->get('bean');
                self::fail('accepted the init method ' . $initMethod);
            } catch (RuntimeException $e) {
                self::assertSame(
                    'The bean bean names the init method ' . $initMethod . '(), which ' . RecordingBean::class
                    . ' does not have as a public method.',
                    $e->getMessage(),
                );
            }
        }
    }

    public function testTheParentsDefinitionIsAppliedFirst(): void
    {
        $factory = new BeanFactory(['beans' => [
            'parent' => [
                'class' => RecordingBean::class,
                'properties' => ['Config' => 'other'],
                'initMethod' => 'init',
                'scope' => 'singleton',
            ],
            'child' => [
                'class' => RecordingBean::class,
                'parent' => 'parent',
                'properties' => ['Config' => 'dependency'],
                'initMethod' => 'initAgain',
                'scope' => 'prototype',
            ],
        ]]);

        $child = $factory->get('child');

        self::assertInstanceOf(RecordingBean::class, $child);
        self::assertSame(
            ['setOther', 'setBeanFactory', 'init', 'setDependency', 'setBeanFactory', 'initAgain'],
            $child->getCalls(),
        );
        self::assertNotSame($child, $factory->get('child'), 'the child\'s own scope counts');
        self::assertSame(['beansCreated' => 2], $factory->getStatistics(), 'two children, the parent never');
    }

    public function testAParentChainIsFollowed(): void
    {
        $factory = new BeanFactory(['beans' => [
            'grandparent' => ['class' => PlainBean::class, 'initMethod' => 'init'],
            'parent' => ['class' => PlainBean::class, 'parent' => 'grandparent', 'initMethod' => 'initAgain'],
            'child' => ['class' => RecordingBean::class, 'parent' => 'parent'],
        ]]);

        $child = $factory->get('child');

        self::assertInstanceOf(RecordingBean::class, $child);
        self::assertSame(
            ['setBeanFactory', 'init', 'setBeanFactory', 'initAgain', 'setBeanFactory'],
            $child->getCalls(),
        );
    }

    public function testAMissingParentIsRefused(): void
    {
        $factory = new BeanFactory(['beans' => ['child' => ['class' => RecordingBean::class, 'parent' => 'missing']]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean child names the parent missing, which has no configuration.');

        $factory->get('child');
    }

    public function testACycleOfParentsIsRefused(): void
    {
        $factory = new BeanFactory(['beans' => [
            'a' => ['class' => RecordingBean::class, 'parent' => 'b'],
            'b' => ['class' => RecordingBean::class, 'parent' => 'c'],
            'c' => ['class' => RecordingBean::class, 'parent' => 'a'],
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean a has a cycle of parents: a > b > c > a.');

        $factory->get('a');
    }

    public function testABeanCannotBeItsOwnParent(): void
    {
        $factory = new BeanFactory(['beans' => ['a' => ['class' => RecordingBean::class, 'parent' => 'a']]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean a has a cycle of parents: a > a.');

        $factory->get('a');
    }

    #[DataProvider('provideMalformedDefinitions')]
    public function testAMalformedDefinitionIsRefused(mixed $definition, string $message): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => $definition]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $factory->get('bean');
    }

    public function testAMalformedParentDefinitionIsRefused(): void
    {
        $factory = new BeanFactory(['beans' => [
            'parent' => ['class' => RecordingBean::class, 'scope' => 'session'],
            'child' => ['class' => RecordingBean::class, 'parent' => 'parent'],
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean parent has a scope other than singleton and prototype.');

        $factory->get('child');
    }

    public function testBeansOfAnotherTypeAreRefused(): void
    {
        $factory = new BeanFactory(['beans' => 'none']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configuration\'s beans must be an array, not string.');

        $factory->has('bean');
    }

    public function testAConfigurationReplacedByAnythingElseIsRefused(): void
    {
        $factory = new BeanFactory([]);
        $factory->set('Config', 'none');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The bean Config is no configuration: Expected an array keyed by strings, got string.',
        );

        $factory->getConfig();
    }

    public function testABeanIsOfItsConfiguredClass(): void
    {
        $factory = new BeanFactory(['beans' => ['bean' => ['class' => RecordingBean::class]]]);

        self::assertTrue($factory->is(new RecordingBean(), 'bean'));
        self::assertFalse($factory->is(new PlainBean(), 'bean'));
        self::assertFalse($factory->is(RecordingBean::class, 'bean'), 'a class name is no bean');
        self::assertFalse($factory->is(new RecordingBean(), 'unknown'));
    }
}
