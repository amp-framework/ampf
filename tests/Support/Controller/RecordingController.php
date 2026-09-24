<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\Controller\ControllerInterface;
use ampf\Controller\ControllerInterruptedException;
use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;

/**
 * A controller that records its lifecycle, and interrupts it where the test says.
 */
final class RecordingController implements ControllerInterface
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    private ?string $interruptIn = null;

    /** Throws a ControllerInterruptedException in this hook: beforeAction, execute or afterAction. */
    public function interruptIn(string $hook): self
    {
        $this->interruptIn = $hook;

        return $this;
    }

    public function beforeAction(): void
    {
        $this->record('beforeAction', 'beforeAction');
    }

    public function execute(?string $id = null, ?string $slug = null): void
    {
        $this->record('execute', 'execute(' . var_export($id, true) . ', ' . var_export($slug, true) . ')');
    }

    public function afterAction(): void
    {
        $this->record('afterAction', 'afterAction');
    }

    public function setRequest(CliRequestInterface|HttpRequestInterface $request): void
    {
        $this->calls[] = 'setRequest(' . $request::class . ')';
    }

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    private function record(string $hook, string $call): void
    {
        $this->calls[] = $call;

        if ($this->interruptIn === $hook) {
            throw new ControllerInterruptedException('interrupted in ' . $hook);
        }
    }
}
