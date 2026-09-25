<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Generator;

use Doctrine\ORM\Mapping\Entity;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

/**
 * Renders an application's bean access traits from two sources: the singleton beans of the merged configuration that
 * are keyed by one of the application's interfaces — what a trait asks the bean factory for —, and the entity classes
 * whose Doctrine #[Entity] names a repository. Every name is derived: with the namespace `App`,
 * `App\Service\User\UserServiceInterface` becomes `App\BeanAccess\Service\UserServiceAccess` with getUserService(),
 * and `App\Doctrine\Repository\UserRepo` (the repository of `App\Doctrine\Entity\UserEntity`) becomes
 * `App\BeanAccess\Doctrine\Repository\UserRepoAccess` with getUserRepo(). A trait sits one namespace level above its
 * type — the type's own sub-namespace is dropped —, except under `Doctrine\`, where it keeps the type's namespace.
 *
 * A prototype bean gets no trait (a trait keeps the bean it fetched), and a trait written by hand is named in
 * `$handWritten`, so that the stale check leaves it alone.
 */
class BeanAccessGenerator
{
    /** The namespace of the framework's traits that the generated ones use. */
    protected const string FRAMEWORK_NAMESPACE = 'ampf\BeanAccess\\';

    /**
     * @param string $projectRoot the directory the paths are relative to
     * @param string $namespace the application's namespace (no leading or trailing backslash), whose classes are in
     *     the source directory (PSR-4)
     * @param string $sourceDirectory the directory of the namespace, relative to the project root
     * @param string $accessNamespace the namespace of the traits, relative to the application's
     * @param ?string $entityNamespace the namespace of the entities, relative to the application's; null for none
     * @param list<string> $handWritten the traits written by hand: paths under the traits' directory, a file or a
     *     directory ending in "/"
     * @param int $lineLength the length PHPCS wraps a line at
     *
     * @throws RuntimeException when the project root is no directory
     */
    public function __construct(
        protected string $projectRoot,
        protected string $namespace,
        protected string $sourceDirectory = 'src',
        protected string $accessNamespace = 'BeanAccess',
        protected ?string $entityNamespace = 'Doctrine\Entity',
        protected array $handWritten = [],
        protected int $lineLength = 120,
    ) {
        if (!is_dir($projectRoot)) {
            throw new RuntimeException('The project root ' . $projectRoot . ' is no directory.');
        }
    }

    /**
     * @param array<mixed> $beans the `beans` of the merged configuration
     *
     * @return array<string, string> project-relative path => contents, sorted by path
     */
    public function generate(array $beans): array
    {
        $files = [];

        foreach ($this->interfaceBeans($beans) as $interface) {
            $files[$this->pathFor($interface)] = $this->renderServiceTrait($interface);
        }

        foreach ($this->repositories() as $entity => $repository) {
            $files[$this->pathFor($repository)] = $this->renderRepositoryTrait($entity, $repository);
        }

        ksort($files);

        return $files;
    }

    /**
     * The singleton beans keyed by an interface of the application, in the configuration's order.
     *
     * @param array<mixed> $beans
     *
     * @return list<class-string>
     */
    public function interfaceBeans(array $beans): array
    {
        $interfaces = [];

        foreach ($beans as $id => $definition) {
            if (
                is_string($id)
                && str_starts_with($id, $this->namespace . '\\')
                && interface_exists($id)
                && is_array($definition)
                && ($definition['scope'] ?? null) !== 'prototype'
            ) {
                $interfaces[] = $id;
            }
        }

        return $interfaces;
    }

    /**
     * The entities under the entity namespace that name a repository class, entity => repository, sorted by entity.
     *
     * @return array<class-string, class-string>
     *
     * @throws RuntimeException for a file that does not declare the type its path names
     */
    public function repositories(): array
    {
        if ($this->entityNamespace === null) {
            return [];
        }

        $repositories = [];

        foreach ($this->typesUnder($this->entityNamespace) as $type) {
            if (!class_exists($type)) {
                // An interface or a trait among the entities
                continue;
            }

            foreach (new ReflectionClass($type)->getAttributes(Entity::class) as $attribute) {
                $repository = $attribute->newInstance()->repositoryClass;

                if ($repository !== null && class_exists($repository)) {
                    $repositories[$type] = $repository;
                }
            }
        }

        ksort($repositories);

        return $repositories;
    }

    /**
     * `App\Service\Api\Caller\ApiCallerServiceInterface` => `src/BeanAccess/Service/Api/ApiCallerServiceAccess.php`,
     * `App\Doctrine\Repository\UserRepo` => `src/BeanAccess/Doctrine/Repository/UserRepoAccess.php`.
     *
     * @throws RuntimeException for a type outside the application's namespace
     */
    public function pathFor(string $type): string
    {
        $suffix = str_replace('\\', '/', $this->traitNamespaceSuffix($type));

        return $this->accessDirectory() . ($suffix === '' ? '' : $suffix . '/') . $this->traitName($type) . '.php';
    }

    /**
     * The traits under the traits' directory that the sources do not generate any more (a renamed interface, a removed
     * bean), so that none lingers unnoticed: every *Access.php but the abstract ones and those written by hand.
     *
     * @param array<string, string> $generated
     *
     * @return list<string> project-relative paths, sorted
     */
    public function staleFiles(array $generated): array
    {
        $directory = $this->projectRoot . '/' . $this->accessDirectory();

        if (!is_dir($directory)) {
            return [];
        }

        $stale = [];

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            assert($file instanceof SplFileInfo);
            $relative = substr($file->getPathname(), strlen($directory));
            $path = $this->accessDirectory() . $relative;

            if (
                str_ends_with($relative, 'Access.php')
                && !str_starts_with($file->getFilename(), 'Abstract')
                && !$this->isHandWritten($relative)
                && !isset($generated[$path])
            ) {
                $stale[] = $path;
            }
        }

        sort($stale);

        return $stale;
    }

    /**
     * The generated files whose contents differ from the project's: path => "new" or "changed", sorted by path.
     *
     * @param array<string, string> $generated
     *
     * @return array<string, 'changed'|'new'>
     */
    public function changedFiles(array $generated): array
    {
        $changed = [];

        foreach ($generated as $path => $contents) {
            $file = $this->projectRoot . '/' . $path;

            if (!is_file($file)) {
                $changed[$path] = 'new';
            } elseif (file_get_contents($file) !== $contents) {
                $changed[$path] = 'changed';
            }
        }

        ksort($changed);

        return $changed;
    }

    /**
     * Writes the files into the project, with the directories they need.
     *
     * @param array<string, string> $files project-relative path => contents
     *
     * @throws RuntimeException for a file or a directory that cannot be written
     */
    public function write(array $files): void
    {
        foreach ($files as $path => $contents) {
            $file = $this->projectRoot . '/' . $path;
            $directory = dirname($file);

            // @: a failure is this method's exception, not a warning besides it
            if (!is_dir($directory) && !@mkdir($directory, 0o755, true)) {
                throw new RuntimeException('The directory of the trait ' . $path . ' cannot be created.');
            }

            if (@file_put_contents($file, $contents) === false) {
                throw new RuntimeException('The trait ' . $path . ' cannot be written.');
            }
        }
    }

    /** `UserServiceInterface` => `UserServiceAccess`, `UserRepo` => `UserRepoAccess`. */
    public function traitName(string $type): string
    {
        return $this->baseName($type) . 'Access';
    }

    /** The traits' directory, relative to the project root, with a trailing "/". */
    protected function accessDirectory(): string
    {
        return $this->sourceDirectory . '/' . str_replace('\\', '/', $this->accessNamespace) . '/';
    }

    protected function isHandWritten(string $relative): bool
    {
        foreach ($this->handWritten as $handWritten) {
            if (str_starts_with($relative, $handWritten)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The types a namespace's files are named for (PSR-4), in the order the directory lists them.
     *
     * @return list<string>
     *
     * @throws RuntimeException for a file that does not declare the type its path names
     */
    protected function typesUnder(string $namespace): array
    {
        $directory = $this->projectRoot . '/' . $this->sourceDirectory . '/' . str_replace('\\', '/', $namespace);

        if (!is_dir($directory)) {
            return [];
        }

        $types = [];

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            assert($file instanceof SplFileInfo);

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $type = $this->namespace . '\\' . $namespace . '\\' . str_replace('/', '\\', $relative);

            if (!class_exists($type) && !interface_exists($type) && !trait_exists($type)) {
                throw new RuntimeException('The file ' . $file->getPathname() . ' does not declare ' . $type . '.');
            }

            $types[] = $type;
        }

        return $types;
    }

    protected function renderServiceTrait(string $interface): string
    {
        $short = $this->shortName($interface);
        $base = $this->baseName($interface);
        $property = '__' . lcfirst($base);
        $namespace = $this->traitNamespace($interface);
        $uses = $this->useBlock($namespace, [static::FRAMEWORK_NAMESPACE . 'AbstractAccess', $interface]);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            {$uses}

            trait {$base}Access
            {
                use AbstractAccess;

                protected ?{$short} \${$property} = null;

                public function get{$base}(): {$short}
                {
                    if (\$this->{$property} === null) {
                        \$object = \$this->getBeanFactory()->get({$short}::class);
                        assert(\$object instanceof {$short});
                        \$this->set{$base}(\$object);
                    }

            {$this->assertLine($property, $short)}

                    return \$this->{$property};
                }

            {$this->setterSignature($base, $short)}
                    \$this->{$property} = \$object;
                }
            }

            PHP;
    }

    protected function renderRepositoryTrait(string $entity, string $repository): string
    {
        $short = $this->shortName($repository);
        $entityShort = $this->shortName($entity);
        $base = $this->baseName($repository);
        $property = '__' . lcfirst($base);
        $namespace = $this->traitNamespace($repository);
        $uses = $this->useBlock(
            $namespace,
            [$entity, $repository, static::FRAMEWORK_NAMESPACE . 'Doctrine\Repository\AbstractRepoAccess'],
        );
        // As PHPCS wants it: one line while it fits, then the setter call wrapped, then the inner call as well
        $inner = "\$this->getDoctrineEntityRepository({$entityShort}::class, {$short}::class)";
        $call = "            \$this->set{$base}({$inner});";

        if (strlen($call) > $this->lineLength) {
            $call = "            \$this->set{$base}(\n                {$inner},\n            );";
        }

        if (strlen("                {$inner},") > $this->lineLength) {
            $call = "            \$this->set{$base}(\n"
                . "                \$this->getDoctrineEntityRepository(\n"
                . "                    {$entityShort}::class,\n"
                . "                    {$short}::class,\n"
                . "                ),\n"
                . '            );';
        }

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            {$uses}

            trait {$base}Access
            {
                use AbstractRepoAccess;

                protected ?{$short} \${$property} = null;

                public function get{$base}(): {$short}
                {
                    if (\$this->{$property} === null) {
            {$call}
                    }

            {$this->assertLine($property, $short)}

                    return \$this->{$property};
                }

            {$this->setterSignature($base, $short)}
                    \$this->{$property} = \$object;
                }
            }

            PHP;
    }

    /** The instance assertion, wrapped as PHPCS wraps it once it exceeds the line length. */
    protected function assertLine(string $property, string $short): string
    {
        $line = "        assert(\$this->{$property} instanceof {$short});";

        if (strlen($line) <= $this->lineLength) {
            return $line;
        }

        return "        assert(\n            \$this->{$property} instanceof {$short},\n        );";
    }

    /** The setter's signature, wrapped as PHPCS wraps it once it exceeds the line length. */
    protected function setterSignature(string $base, string $short): string
    {
        $line = "    public function set{$base}({$short} \$object): void";

        if (strlen($line) <= $this->lineLength) {
            return $line . "\n    {";
        }

        return "    public function set{$base}(\n        {$short} \$object,\n    ): void {";
    }

    /**
     * The imports of the classes, sorted as PHPCS sorts them; a class of the trait's own namespace needs none.
     *
     * @param list<string> $classes
     */
    protected function useBlock(string $namespace, array $classes): string
    {
        $imports = array_filter($classes, fn (string $class): bool => $this->namespaceOf($class) !== $namespace);
        usort($imports, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return implode("\n", array_map(static fn (string $class): string => 'use ' . $class . ';', $imports));
    }

    protected function traitNamespace(string $type): string
    {
        $suffix = $this->traitNamespaceSuffix($type);

        return $this->namespace . '\\' . $this->accessNamespace . ($suffix === '' ? '' : '\\' . $suffix);
    }

    /**
     * The trait's namespace below the traits' own: the type's, without its own sub-namespace outside `Doctrine\`.
     *
     * @throws RuntimeException for a type outside the application's namespace
     */
    protected function traitNamespaceSuffix(string $type): string
    {
        $prefix = $this->namespace . '\\';

        if (!str_starts_with($type, $prefix)) {
            throw new RuntimeException('The type ' . $type . ' is not in the namespace ' . $this->namespace . '.');
        }

        $parts = explode('\\', substr($type, strlen($prefix)));
        array_pop($parts);

        if (($parts[0] ?? '') !== 'Doctrine') {
            // Service\Api\Caller => Service\Api
            array_pop($parts);
        }

        return implode('\\', $parts);
    }

    protected function namespaceOf(string $class): string
    {
        return substr($class, 0, (int)strrpos($class, '\\'));
    }

    protected function shortName(string $type): string
    {
        return array_last(explode('\\', $type));
    }

    protected function baseName(string $type): string
    {
        $short = $this->shortName($type);

        return str_ends_with($short, 'Interface')
            ? substr($short, 0, -strlen('Interface'))
            : $short;
    }
}
