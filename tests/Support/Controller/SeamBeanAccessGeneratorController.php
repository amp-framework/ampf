<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\BeanAccess\Generator\BeanAccessGenerator;
use ampf\Controller\Cli\BeanAccessGeneratorController;

/**
 * The generator's controller with its generator's creation noted, as an application's subclass that creates its own
 * generator would.
 */
final class SeamBeanAccessGeneratorController extends BeanAccessGeneratorController
{
    /**
     * @var list<mixed>
     */
    private array $arguments = [];

    /**
     * The arguments each generator was created from.
     *
     * @return list<mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    protected function createGenerator(mixed $arguments): BeanAccessGenerator
    {
        $this->arguments[] = $arguments;

        return parent::createGenerator($arguments);
    }
}
