<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\BeanAccess\Generator\BeanAccessGenerator;

/**
 * A generator whose protected methods note their calls and then do their work, as an application's subclass that
 * changes one step would; the types of a namespace come out in the reverse order of the parent's.
 */
final class SeamBeanAccessGenerator extends BeanAccessGenerator
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * The names of the protected methods called so far, each once, sorted.
     *
     * @return list<string>
     */
    public function getCalledMethods(): array
    {
        $methods = array_values(array_unique($this->calls));
        sort($methods);

        return $methods;
    }

    protected function accessDirectory(): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::accessDirectory();
    }

    protected function isHandWritten(string $relative): bool
    {
        $this->calls[] = __FUNCTION__;

        return parent::isHandWritten($relative);
    }

    /**
     * @return list<string>
     */

    protected function typesUnder(string $namespace): array
    {
        $this->calls[] = __FUNCTION__;

        return array_reverse(parent::typesUnder($namespace));
    }

    protected function renderServiceTrait(string $interface): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::renderServiceTrait($interface);
    }

    protected function renderRepositoryTrait(string $entity, string $repository): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::renderRepositoryTrait($entity, $repository);
    }

    protected function assertLine(string $property, string $short): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::assertLine($property, $short);
    }

    protected function setterSignature(string $base, string $short): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::setterSignature($base, $short);
    }

    /**
     * @param list<string> $classes
     */

    protected function useBlock(string $namespace, array $classes): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::useBlock($namespace, $classes);
    }

    protected function traitNamespace(string $type): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::traitNamespace($type);
    }

    protected function traitNamespaceSuffix(string $type): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::traitNamespaceSuffix($type);
    }

    protected function namespaceOf(string $class): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::namespaceOf($class);
    }

    protected function shortName(string $type): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::shortName($type);
    }

    protected function baseName(string $type): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::baseName($type);
    }
}
