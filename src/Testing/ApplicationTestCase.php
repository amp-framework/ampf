<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Bean\BeanFactory;
use ampf\Bean\BeanFactoryAccessInterface;
use ampf\Bootstrap\ApplicationContext;
use ampf\Router\CliRouterInterface;
use ampf\Router\HttpRouterInterface;
use ampf\Service\Hasher\HasherServiceInterface;
use ampf\Service\Session\SessionServiceInterface;
use ampf\Service\XsrfToken\XsrfTokenServiceInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The base of an application's tests that run the application whole: real requests and commands through its router,
 * controllers, templates and services, in the test's process. A subclass names the project root (projectRoot()).
 *
 * Every simulated request runs in a scope of its own — a new bean factory over the application's configuration, as a
 * request in production runs in a process of its own: what a request changed is in the session or the database, not in
 * an object the test holds. The configuration is the framework's config/default.php and config/<transport>.php, the
 * application's two, and its test configuration in place of config/local.php (configurationFiles()). In every scope
 * the harness's doubles stand in for the framework's services — a hasher at bcrypt's lowest cost, the browser's
 * session ($session) — and the test's own beans for the configured ones
 * (useBean()), a clock that stands still among them where the application has one. A scope is released when the next request starts, or when the test ends.
 *
 * PHP's error log goes to a file of the test's own, and a test fails when the application wrote to it: a failure the
 * application caught and logged is a failure all the same. A test that provokes one says so (expectLoggedFailure())
 * and reads the log (errorLog()).
 *
 * A subclass that overrides setUp() or tearDown() calls the parent's. The hooks scopeCreated(), scopeReleased() and
 * beforeDispatch() do nothing here: a subclass adds there what every scope needs (a database, an account).
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
abstract class ApplicationTestCase extends TestCase
{
    /**
     * The session of the browser the requests come from: kept from one request to the next, as a browser keeps its
     * cookie. useSession() continues as another browser.
     */
    protected MemorySessionService $session;

    /**
     * The address the requests come from (REMOTE_ADDR), which a test may change between them.
     */
    protected string $remoteAddress = '192.0.2.1';

    /**
     * The beans the test put in place of the configured ones (useBean()), by id.
     *
     * @var array<string, object>
     */
    protected array $beans = [];

    /**
     * The settings the test made (configure()), by domain and key.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $settings = [];

    /**
     * The configuration of each transport, booted at its first use in the test.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $configurations = [];

    /**
     * The scopes not released yet, in the order they were created.
     *
     * @var list<BeanFactory>
     */
    protected array $scopes = [];

    /**
     * The exit code of the last command (cli()).
     */
    protected int $cliExitCode = 0;

    /**
     * The file PHP's error log goes to during the test.
     */
    protected string $errorLogFile = '';

    /**
     * PHP's error log before the test, which tearDown() puts back.
     */
    protected string $previousErrorLog = '';

    protected bool $errorLogExpected = false;

    /** The application's directory, the one with config/ and tests/ in it. */
    abstract protected static function projectRoot(): string;

    /**
     * The error log of the test's own and a new browser's session.
     *
     * @throws RuntimeException when there is no temporary file for the error log
     */
    protected function setUp(): void
    {
        $this->errorLogFile = $this->newErrorLogFile()
            ?: throw new RuntimeException('There is no temporary file for the error log.');
        $this->previousErrorLog = (string)ini_set('error_log', $this->errorLogFile);
        $this->session = new MemorySessionService();
    }

    /** The scopes released, PHP's error log back as it was, and the test failed when the application wrote to it. */
    protected function tearDown(): void
    {
        $this->releaseScopes();

        $logged = $this->errorLog();
        ini_set('error_log', $this->previousErrorLog);
        unlink($this->errorLogFile);

        if (!$this->errorLogExpected) {
            self::assertSame(
                '',
                $logged,
                'The application wrote to its error log, which the test did not expect (expectLoggedFailure()).',
            );
        }
    }

    /**
     * A GET request of the path, through the application.
     *
     * @param array<string, string|array<mixed>> $get the query (the path carries none)
     * @param array<string, string> $cookie
     * @param array<string, string> $server what the web server passes on of the request's headers (`HTTP_REFERER`)
     */
    protected function get(string $path, array $get = [], array $cookie = [], array $server = []): TestHttpRequest
    {
        return $this->dispatch(new TestHttpRequest($path, $get, [], 'GET', $cookie, $server));
    }

    /**
     * A POST of the form to the path, as the application's own forms send it: the one-time token travels in the query,
     * issued into the session as a rendered form's is (`$withToken = false` sends none).
     *
     * @param array<string, string|array<mixed>> $post the form
     * @param array<string, string|array<mixed>> $get the query
     * @param array<string, string> $cookie
     * @param array<string, string> $server what the web server passes on of the request's headers (`HTTP_REFERER`)
     * @param array<string, string|array<mixed>> $files the form's files in `$_FILES`' shape, their temporary paths
     *     files the test wrote (TestHttpRequest)
     */
    protected function post(
        string $path,
        array $post = [],
        array $get = [],
        bool $withToken = true,
        array $cookie = [],
        array $server = [],
        array $files = [],
    ): TestHttpRequest {
        if ($withToken) {
            $tokens = $this->newScope('http')->get(XsrfTokenServiceInterface::class);
            assert($tokens instanceof XsrfTokenServiceInterface);

            $this->session->open();
            $get[$tokens->getTokenIDForRequest()] = $tokens->getNewToken();
        }

        return $this->dispatch(new TestHttpRequest($path, $get, $post, 'POST', $cookie, $server, files: $files));
    }

    /**
     * Runs the request through the application's router in a new scope, as a request from $remoteAddress with the
     * browser's session; the request returned holds the answer (its status, headers, cookies, redirect or page).
     */
    protected function dispatch(TestHttpRequest $request): TestHttpRequest
    {
        $this->session->open();
        $this->releaseScopes();

        $scope = $this->newScope('http');
        $request->setRemoteAddress($this->remoteAddress);
        $request->setBeanFactory($scope);
        $scope->set('Request', $request);
        $this->beforeDispatch($request, $scope);

        $router = $scope->get('Router');
        assert($router instanceof HttpRouterInterface);
        $router->route($request);

        return $request;
    }

    /**
     * Runs a command through the application's command line router in a new scope, and returns what it would print;
     * cliExitCode() is its exit code.
     *
     * @param list<string> $arguments the route followed by its arguments, as they follow `php bin/index.php`
     */
    protected function cli(array $arguments): string
    {
        $this->releaseScopes();

        $scope = $this->newScope('cli');
        $request = new TestCliRequest($arguments);
        $request->setBeanFactory($scope);
        $scope->set('Request', $request);
        $this->beforeDispatch($request, $scope);

        $router = $scope->get('Router');
        assert($router instanceof CliRouterInterface);
        $router->route($request);
        $this->cliExitCode = $request->getExitCode();

        return $request->getResponseBody();
    }

    /** The exit code of the last command: 0, or what a failing command set. */
    protected function cliExitCode(): int
    {
        return $this->cliExitCode;
    }

    /**
     * A bean of a new scope of the transport, with the session and the settings as they are now: for a
     * service no request reaches the way the test needs.
     */
    protected function bean(string $id, string $transport = 'http'): mixed
    {
        return $this->newScope($transport)->get($id);
    }

    /**
     * Puts the object in place of the bean in every scope from now on — the same object in each, handed each scope's
     * bean factory when it takes one.
     */
    protected function useBean(string $id, object $bean): void
    {
        $this->beans[$id] = $bean;
    }

    /** A setting of the configuration's `configuration.service` for every scope from now on. */
    protected function configure(string $domain, string $key, mixed $value): void
    {
        $this->settings[$domain][$key] = $value;
    }

    /** Continues as another browser: the requests from now on carry this session. */
    protected function useSession(MemorySessionService $session): void
    {
        $this->session = $session;
    }

    /** Everything written to PHP's error log during the test so far. */
    protected function errorLog(): string
    {
        return (string)file_get_contents($this->errorLogFile);
    }

    /**
     * The test provokes a failure that the application logs: it reads errorLog() and says what must be in it. Without
     * this, anything in the log fails the test.
     */
    protected function expectLoggedFailure(): void
    {
        $this->errorLogExpected = true;
    }

    /**
     * A new file in the system's temporary directory for PHP's error log during the test, false when there is none: the
     * one place it is made.
     */
    protected function newErrorLogFile(): false|string
    {
        return tempnam(sys_get_temp_dir(), 'ampf-error-log-');
    }

    /**
     * The files the configuration of the transport is booted from, in their order: the framework's config/default.php
     * and config/<transport>.php (beside this class: vendor/amp-framework/ampf in an application), the application's
     * config/default.php and config/<transport>.php, and its tests/Support/config/integration.php in place of
     * config/local.php.
     *
     * @return list<string>
     */
    protected function configurationFiles(string $transport): array
    {
        $framework = dirname(__DIR__, 2) . '/config/';
        $application = static::projectRoot() . '/';

        return [
            $framework . 'default.php',
            $framework . $transport . '.php',
            $application . 'config/default.php',
            $application . 'config/' . $transport . '.php',
            $application . 'tests/Support/config/integration.php',
        ];
    }

    /**
     * The configuration a scope of the transport runs on: the files of configurationFiles(), booted once in the test,
     * with the test's settings in its `configuration.service`.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException for a file that does not exist
     */
    protected function configuration(string $transport): array
    {
        if (!isset($this->configurations[$transport])) {
            $files = $this->configurationFiles($transport);

            foreach ($files as $file) {
                if (!is_file($file)) {
                    throw new RuntimeException('The configuration file ' . $file . ' does not exist.');
                }
            }

            $this->configurations[$transport] = ApplicationContext::boot($files);
        }

        $config = $this->configurations[$transport];

        foreach ($this->settings as $domain => $values) {
            $domains = (array)($config['configuration.service'] ?? []);
            $domains[$domain] = [...(array)($domains[$domain] ?? []), ...$values];
            $config['configuration.service'] = $domains;
        }

        return $config;
    }

    /**
     * A new scope of the transport: a bean factory over its configuration, with the harness's doubles and the test's
     * beans in place of the configured ones. It is released when the next request starts, or when the test ends.
     */
    protected function newScope(string $transport): BeanFactory
    {
        $scope = new BeanFactory($this->configuration($transport));
        $scope->set(HasherServiceInterface::class, new CheapHasherService());
        $scope->set(SessionServiceInterface::class, $this->session);

        foreach ($this->beans as $id => $bean) {
            if ($bean instanceof BeanFactoryAccessInterface) {
                $bean->setBeanFactory($scope);
            }

            $scope->set($id, $bean);
        }

        $this->scopes[] = $scope;
        $this->scopeCreated($scope, $transport);

        return $scope;
    }

    /** Releases the scopes created so far, in their order (scopeReleased()). */
    protected function releaseScopes(): void
    {
        foreach ($this->scopes as $scope) {
            $this->scopeReleased($scope);
        }

        $this->scopes = [];
    }

    /** A scope was created: the doubles and the test's beans are in place, and nothing of it has run. */
    protected function scopeCreated(BeanFactory $scope, string $transport): void
    {
        // Nothing to add; a subclass adds what every scope needs
    }

    /** The scope is done with: the next request starts, or the test ends. */
    protected function scopeReleased(BeanFactory $scope): void
    {
        // Nothing to close; a subclass closes what a scope opened
    }

    /** The request is about to go to the router of its scope, where it is the bean `Request`. */
    protected function beforeDispatch(TestCliRequest|TestHttpRequest $request, BeanFactory $scope): void
    {
        // Nothing to prepare; a subclass prepares what every request needs
    }
}
