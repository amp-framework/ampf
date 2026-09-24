<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Bean;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\Bean\BeanFactoryInterface;

/**
 * A bean that records what the bean factory does to it, in its order.
 */
class RecordingBean implements BeanFactoryAccessInterface
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    private ?BeanFactoryInterface $beanFactory = null;

    private mixed $dependency = null;

    public function getBeanFactory(): BeanFactoryInterface
    {
        assert($this->beanFactory instanceof BeanFactoryInterface);

        return $this->beanFactory;
    }

    public function setBeanFactory(BeanFactoryInterface $beanFactory): void
    {
        $this->calls[] = 'setBeanFactory';
        $this->beanFactory = $beanFactory;
    }

    public function setDependency(mixed $dependency): void
    {
        $this->calls[] = 'setDependency';
        $this->dependency = $dependency;
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- a setter the test only counts
    public function setOther(mixed $dependency): void
    {
        $this->calls[] = 'setOther';
    }

    public function init(): void
    {
        $this->calls[] = 'init';
    }

    public function initAgain(): void
    {
        $this->calls[] = 'initAgain';
    }

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getDependency(): mixed
    {
        return $this->dependency;
    }

    protected function hidden(): void
    {
        $this->calls[] = 'hidden';
    }
}
