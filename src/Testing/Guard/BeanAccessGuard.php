<?php

declare(strict_types=1);

namespace ampf\Testing\Guard;

use ampf\Bean\BeanFactory;
use ampf\Controller\Cli\BeanAccessGeneratorController;
use ampf\Testing\TestCliRequest;

/**
 * An application's access traits are exactly what the framework's generator writes, configured as its config/cli.php
 * configures it (`beanAccessGenerator`): every trait of a bean or a repository there and unchanged, none that nothing
 * generates any more. The check is the generator's own (`check`, BeanAccessGeneratorController): a new bean without its
 * trait, a trait edited by hand and a stale one fail with its report. An application extends it in one class that names
 * its project root (projectRoot()).
 */
abstract class BeanAccessGuard extends AbstractGuard
{
    public function testTheAccessTraitsAreTheGeneratorsOutput(): void
    {
        $scope = new BeanFactory(static::configuration('cli'));
        $request = new TestCliRequest();
        $scope->set('Request', $request);

        $generator = $scope->get('BeanAccessGeneratorController');
        assert($generator instanceof BeanAccessGeneratorController);
        $generator->execute('check');

        self::assertSame(
            0,
            $request->getExitCode(),
            'The access traits differ from what the generator writes (BeanAccessGeneratorController):' . PHP_EOL
            . rtrim($request->getResponseBody()),
        );
    }
}
