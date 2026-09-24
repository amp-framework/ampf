<?php

declare(strict_types=1);

namespace ampf\Controller\Cli;

use ampf\BeanAccess\Generator\BeanAccessGenerator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Writes the bean access traits the BeanAccessGenerator derives from the merged configuration's beans and the entity
 * classes. With the argument `check` it writes nothing: it lists what would change and ends with the exit code 1 when
 * anything would, or when a trait is stale. A stale trait, one nothing generates any more, is listed in both modes,
 * for a person to delete. The configuration's `beanAccessGenerator` holds the generator's arguments by name:
 *
 *     'beanAccessGenerator' => [
 *         'projectRoot' => dirname(__DIR__),
 *         'namespace' => 'App',
 *         'handWritten' => ['Request/'],
 *     ],
 *
 * config/cli.php defines the bean 'BeanAccessGeneratorController'; the application routes a command to it.
 */
class BeanAccessGeneratorController extends AbstractController
{
    /**
     * @throws InvalidArgumentException for an argument other than `check`
     * @throws RuntimeException when the configuration names no generator
     */
    public function execute(?string $mode = null): void
    {
        if ($mode !== null && $mode !== 'check') {
            throw new InvalidArgumentException('The generator takes the argument check, or none; not ' . $mode . '.');
        }

        $config = $this->getBeanFactory()->getConfig();
        $beans = $config['beans'] ?? [];

        if (!is_array($beans)) {
            throw new RuntimeException(
                'The configuration\'s beans must be an array, not ' . get_debug_type($beans) . '.',
            );
        }

        $generator = $this->createGenerator($config['beanAccessGenerator'] ?? null);
        $files = $generator->generate($beans);
        $changed = $generator->changedFiles($files);
        $stale = $generator->staleFiles($files);

        if ($mode === null) {
            $generator->write(array_intersect_key($files, $changed));
        }

        $output = '';

        foreach ($changed as $path => $change) {
            $output .= str_pad($change, 8) . $path . PHP_EOL;
        }

        foreach ($stale as $path) {
            $output .= 'stale   ' . $path . ' (not generated any more; delete it by hand)' . PHP_EOL;
        }

        $output .= count($files) . ' traits, ' . count(
            $changed,
        ) . ($mode === null ? ' written' : ' would change') . PHP_EOL;
        $this->getRequest()->setResponse($output);

        if ($mode !== null && ($changed !== [] || $stale !== [])) {
            $this->getRequest()->setExitCode(1);
        }
    }

    /**
     * The generator whose arguments the configuration's `beanAccessGenerator` names: `projectRoot` and `namespace`,
     * and the optional ones by their names.
     *
     * @throws RuntimeException for arguments that are missing, unknown or of the wrong type
     */
    protected function createGenerator(mixed $arguments): BeanAccessGenerator
    {
        if (!is_array($arguments) || !isset($arguments['projectRoot'], $arguments['namespace'])) {
            throw new RuntimeException(
                'The configuration\'s beanAccessGenerator must name the generator\'s projectRoot and namespace.',
            );
        }

        foreach ($arguments as $name => $value) {
            $fits = match ($name) {
                'projectRoot', 'namespace', 'sourceDirectory', 'accessNamespace' => is_string($value),
                'entityNamespace' => $value === null || is_string($value),
                'handWritten' => is_array($value) && array_is_list($value) && array_filter(
                    $value,
                    is_string(...),
                ) === $value,
                'lineLength' => is_int($value),
                default => throw new RuntimeException(
                    'The configuration\'s beanAccessGenerator has the unknown argument ' . $name . '.',
                ),
            };

            if (!$fits) {
                throw new RuntimeException(
                    'The configuration\'s beanAccessGenerator.' . $name . ' has the wrong type.',
                );
            }
        }

        /** @var array{projectRoot: string, namespace: string, sourceDirectory?: string, accessNamespace?: string, entityNamespace?: ?string, handWritten?: list<string>, lineLength?: int} $arguments */
        return new BeanAccessGenerator(...$arguments);
    }
}
