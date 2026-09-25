<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Bean;

use ampf\Bean\BeanFactory;

/**
 * A bean factory whose protected methods note their calls and then do their work, as an application's subclass that
 * changes one step of the creation would.
 */
final class SeamBeanFactory extends BeanFactory
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @param list<string> $chain
     */

    protected function configure(string $beanID, object $bean, array $definition, array $chain): void
    {
        $this->calls[] = 'configure ' . $beanID;

        parent::configure($beanID, $bean, $definition, $chain);
    }

    /**
     * @return ?array{class: class-string, scope?: string, properties?: array<string, string>, initMethod?: string, parent?: string}
     */

    protected function getDefinition(string $beanID): ?array
    {
        $this->calls[] = 'getDefinition ' . $beanID;

        return parent::getDefinition($beanID);
    }

    /**
     * @return array<mixed>
     */

    protected function getDefinitions(): array
    {
        $this->calls[] = 'getDefinitions';

        return parent::getDefinitions();
    }
}
