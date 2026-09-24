<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;

/**
 * A controller whose execute() collects whatever arguments it gets.
 */
final class VariadicController implements ControllerInterface
{
    /**
     * @var array<int|string, string>
     */
    private array $arguments = [];

    public function beforeAction(): void
    {
        // Nothing to prepare
    }

    public function execute(string ...$arguments): void
    {
        $this->arguments = $arguments;
    }

    public function afterAction(): void
    {
        // Nothing to wrap
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- the interface's signature
    public function setRequest(CliRequestInterface|HttpRequestInterface $request): void
    {
        // The arguments are all this controller wants
    }

    /**
     * @return array<int|string, string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
