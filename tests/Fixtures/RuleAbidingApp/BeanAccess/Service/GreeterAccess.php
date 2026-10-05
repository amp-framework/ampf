<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleAbidingApp\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Tests\Fixtures\RuleAbidingApp\Service\Greeter\GreeterInterface;

trait GreeterAccess
{
    use AbstractAccess;

    protected ?GreeterInterface $__greeter = null;

    public function getGreeter(): GreeterInterface
    {
        if ($this->__greeter === null) {
            $object = $this->getBeanFactory()->get(GreeterInterface::class);
            assert($object instanceof GreeterInterface);
            $this->setGreeter($object);
        }

        assert($this->__greeter instanceof GreeterInterface);

        return $this->__greeter;
    }

    public function setGreeter(GreeterInterface $object): void
    {
        $this->__greeter = $object;
    }
}
