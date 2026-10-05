<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Bean\BeanFactory;
use ampf\Bean\BeanFactoryInterface;
use ampf\Doctrine\EntityManagerFactoryInterface;
use ampf\Router\CliRouter;
use ampf\Router\HttpRouter;
use ampf\Service\Configuration\ConfigurationServiceInterface;
use ampf\Service\Hasher\HasherServiceInterface;
use ampf\Service\Session\SessionServiceInterface;
use ampf\Testing\ApplicationTestCase;
use ampf\Testing\CheapHasherService;
use ampf\Testing\ExpectsExactMessage;
use ampf\Testing\MemorySessionService;
use ampf\Testing\TestCliRequest;
use ampf\Testing\TestHttpRequest;
use ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity;
use ampf\Tests\Support\Controller\GreetingHttpController;
use ampf\Tests\Support\Controller\RecordingController;
use ampf\Tests\Support\TemporaryDirectory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * The harness over the fixture application (tests/Fixtures/App), used as an application's test uses it. The fixture's
 * database is SQLite in memory, a new one for every scope: scopeCreated() gives it the schema and scopeReleased()
 * closes it — the way a subclass adds what every scope needs. The hooks note their calls, each scope by its number.
 */
#[CoversClass(ApplicationTestCase::class)]
final class ApplicationTestCaseTest extends ApplicationTestCase
{
    use ExpectsExactMessage;

    /**
     * @var list<string>
     */
    private array $hooks = [];

    /**
     * The scopes the hooks saw, in their order: a scope's number is its position, from 1.
     *
     * @var list<BeanFactory>
     */
    private array $scopesSeen = [];

    /**
     * The requests beforeDispatch() was handed, with their scopes.
     *
     * @var list<array{TestCliRequest|TestHttpRequest, BeanFactory}>
     */
    private array $dispatched = [];

    /**
     * A file the configuration names besides the application's, which does not exist (configurationFiles()).
     */
    private ?string $missingFile = null;

    /**
     * Whether the system has no temporary file for the error log (newErrorLogFile()).
     */
    private bool $noTemporaryFile = false;

    protected static function projectRoot(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/App';
    }

    public function testTheFilesAreTheFrameworksTheApplicationsAndTheTestConfiguration(): void
    {
        $framework = dirname(__DIR__, 3) . '/config/';
        $application = dirname(__DIR__, 2) . '/Fixtures/App/';

        self::assertSame(
            [
                $framework . 'default.php',
                $framework . 'cli.php',
                $application . 'config/default.php',
                $application . 'config/cli.php',
                $application . 'tests/Support/config/integration.php',
            ],
            $this->configurationFiles('cli'),
        );
        self::assertSame(
            'Hello from the tests',
            $this->configurationService()->get('greeting', '.app'),
            'the test configuration is booted last',
        );
    }

    public function testTheConfigurationIsBootedOnceInATest(): void
    {
        $first = $this->configuration('http');

        self::assertSame($first['doctrine'], $this->configuration('http')['doctrine'], 'the same ORM configuration');
        self::assertNotSame($first['doctrine'], $this->configuration('cli')['doctrine'], 'each transport its own');
        self::assertSame(CliRouter::class, get_debug_type($this->bean('Router', 'cli')));
    }

    public function testAMissingConfigurationFileIsNamed(): void
    {
        $this->missingFile = sys_get_temp_dir() . '/ampf-no-such-file/local.php';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageExactly('The configuration file ' . $this->missingFile . ' does not exist.');

        $this->get('hello/ada');
    }

    public function testARequestGoesThroughTheApplicationsRouterControllerAndTemplates(): void
    {
        $request = $this->get('');

        self::assertSame(200, $request->getResponseStatusCode());
        self::assertStringContainsString('<h1>Welcome, world!</h1>', $request->requireResponse());
        self::assertStringContainsString('<a href="/hello/ada">Ada</a>', $request->requireResponse());
        self::assertSame('<p>Hello ada</p>', $this->get('hello/ada')->requireResponse());
        self::assertSame(HttpRouter::class, get_debug_type($request->getBeanFactory()->get('Router')));
    }

    public function testTheQueryTheCookiesAndTheServersVariablesReachTheRequest(): void
    {
        $request = $this->get('hello/ada', ['tab' => 'all'], ['theme' => 'dark'], ['HTTP_REFERER' => 'http://x.test/']);

        self::assertSame('GET', $request->getServerParam('REQUEST_METHOD'));
        self::assertSame('/hello/ada?tab=all', $request->getServerParam('REQUEST_URI'));
        self::assertSame('all', $request->getGetString('tab'));
        self::assertSame('dark', $request->getCookieParam('theme'));
        self::assertSame('http://x.test/', $request->getServerParam('HTTP_REFERER'));
    }

    public function testARequestTheTestBuiltIsDispatchedAsItIs(): void
    {
        $request = new TestHttpRequest('theme/dark');

        self::assertSame($request, $this->dispatch($request));
        $request->assertRedirect('/', 302);
        self::assertSame(['theme' => ['value' => 'dark', 'expires' => 0]], $request->getCookiesSet());
    }

    public function testAFormIsPostedWithAOneTimeTokenAsTheApplicationsFormsAre(): void
    {
        $request = $this->post(
            'notes',
            ['text' => 'Buy milk'],
            ['tab' => 'all'],
            true,
            ['c' => '1'],
            ['HTTP_X' => 'y'],
        );

        $request->assertRedirect('/notes');
        self::assertSame('POST', $request->getServerParam('REQUEST_METHOD'));
        self::assertSame('all', $request->getGetString('tab'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $request->getGetString('stkn'));
        self::assertSame('1', $request->getCookieParam('c'));
        self::assertSame('y', $request->getServerParam('HTTP_X'));
        self::assertSame(['Buy milk'], array_map(
            static fn (NoteEntity $note): string => $note->getText(),
            $this->entityManagerOf($request->getBeanFactory())->getRepository(NoteEntity::class)->findAll(),
        ));
        $this->post('notes', ['text' => 'Buy bread'])->assertRedirect('/notes');
    }

    public function testAFormCarriesTheFilesTheTestWrote(): void
    {
        $directory = new TemporaryDirectory('ampf-upload');

        try {
            $path = $directory->getPath() . '/php1';
            file_put_contents($path, 'Hello');

            $request = $this->post('upload', files: ['files' => [
                'name' => 'a.txt',
                'type' => 'text/plain',
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => 5,
            ]]);

            self::assertSame('a.txt 5 0 Hello', $request->requireResponse(), 'moved and read by the controller');
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $request->getGetString('stkn'));
        } finally {
            $directory->remove();
        }
    }

    public function testAFormWithoutTheTokenIsRefused(): void
    {
        $request = $this->post('notes', ['text' => 'Buy milk'], withToken: false);

        self::assertSame(403, $request->getResponseStatusCode());
        self::assertSame('Forbidden', $request->requireResponse());
        self::assertFalse($request->hasGetParam('stkn'));
    }

    public function testTheTokenIsKeptInASessionThatWasClosed(): void
    {
        $this->session->close();

        $this->post('notes', ['text' => 'Buy milk'])->assertRedirect('/notes');
    }

    public function testEveryRequestRunsInAScopeOfItsOwn(): void
    {
        $first = $this->get('hello/ada');
        $second = $this->get('hello/ada');

        self::assertNotSame($first->getBeanFactory(), $second->getBeanFactory());
        self::assertNotSame($first->getBeanFactory()->get('Router'), $second->getBeanFactory()->get('Router'));
        self::assertSame($second, $second->getBeanFactory()->get('Request'));
    }

    public function testTheSessionIsTheBrowsersFromOneRequestToTheNext(): void
    {
        $request = $this->get('counter');

        self::assertSame('Visits: 1', $request->requireResponse());
        self::assertSame('Visits: 2', $this->get('counter')->requireResponse());
        self::assertSame(2, $this->session->getAttribute('visits'));
        self::assertSame($this->session, $request->getBeanFactory()->get(SessionServiceInterface::class));
    }

    public function testEveryRequestOpensTheSessionAgain(): void
    {
        $this->session->close();

        self::assertSame('Visits: 1', $this->get('counter')->requireResponse());
        self::assertSame(1, $this->session->getAttribute('visits'), 'kept');
    }

    public function testAnotherBrowserHasASessionOfItsOwn(): void
    {
        $this->get('counter');
        $other = new MemorySessionService();
        $this->useSession($other);

        self::assertSame($other, $this->session);
        self::assertSame('Visits: 1', $this->get('counter')->requireResponse());
    }

    public function testTheRequestsComeFromTheTestsAddress(): void
    {
        self::assertSame('192.0.2.1', $this->get('hello/ada')->getServerParam('REMOTE_ADDR'));

        $this->remoteAddress = '198.51.100.7';
        $request = $this->dispatch(new TestHttpRequest('hello/ada', server: ['REMOTE_ADDR' => '203.0.113.9']));

        self::assertSame('198.51.100.7', $request->getServerParam('REMOTE_ADDR'));
    }

    public function testTheHarnesssDoublesStandInForTheServices(): void
    {
        foreach (['http', 'cli'] as $transport) {
            self::assertInstanceOf(CheapHasherService::class, $this->bean(HasherServiceInterface::class, $transport));
            self::assertSame($this->session, $this->bean(SessionServiceInterface::class, $transport));
        }
    }

    public function testATestsBeanStandsInForTheConfiguredOneInEveryScopeFromThenOn(): void
    {
        $recording = new RecordingController();
        $this->useBean('CounterController', $recording);
        $this->get('counter');

        self::assertSame(['beforeAction', 'execute(NULL, NULL)', 'afterAction'], $recording->getCalls());

        $greeting = new GreetingHttpController();
        $this->useBean('HelloController', $greeting);
        $request = $this->get('hello/bob');

        self::assertSame('<p>Hello bob</p>', $request->requireResponse());
        self::assertSame($request->getBeanFactory(), $greeting->getBeanFactory(), 'it has the scope\'s factory');

        $session = new MemorySessionService();
        $this->useBean(SessionServiceInterface::class, $session);

        self::assertSame($session, $this->bean(SessionServiceInterface::class, 'cli'), 'over the harness\'s own');
    }

    public function testABeanComesFromAScopeOfItsOwn(): void
    {
        self::assertNotSame($this->bean('Router'), $this->bean('Router'));
        self::assertSame(HttpRouter::class, get_debug_type($this->bean('Router')));
        self::assertSame(CliRouter::class, get_debug_type($this->bean('Router', 'cli')));
    }

    public function testASettingOfTheTestIsTheApplicationsFromThenOn(): void
    {
        $this->configure('.app', 'audience', 'the testers');
        $this->configure('.new', 'colour', 'blue');

        foreach (['http', 'cli'] as $transport) {
            $settings = $this->configurationService($transport);

            self::assertSame('the testers', $settings->get('audience', '.app'));
            self::assertSame('Hello from the tests', $settings->get('greeting', '.app'), 'the domain\'s others stay');
            self::assertSame('green', $settings->get('colour', '.other'), 'and so do the other domains');
            self::assertSame('blue', $settings->get('colour', '.new'));
        }

        $this->configure('.app', 'audience', 'nobody');

        self::assertSame('nobody', $this->configurationService()->get('audience', '.app'));
    }

    public function testACommandRunsThroughTheCommandLinesRouter(): void
    {
        self::assertSame(0, $this->cliExitCode());
        self::assertSame('Something failed.' . PHP_EOL, $this->cli(['fail']));
        self::assertSame(3, $this->cliExitCode());
        self::assertSame('Hello Ada!' . PHP_EOL, $this->cli(['greet', 'Ada']));
        self::assertSame(0, $this->cliExitCode());
    }

    public function testTheHooksAreWhereASubclassAddsWhatEveryScopeNeeds(): void
    {
        $request = $this->get('hello/ada');
        $this->bean('Router');
        $this->cli(['greet']);
        $this->post('notes', ['text' => 'Buy milk']);

        self::assertSame(
            [
                'created http 1',
                'dispatching a web request 1',
                'created http 2',
                'released 1',
                'released 2',
                'created cli 3',
                'dispatching a command 3',
                'created http 4',
                'released 3',
                'released 4',
                'created http 5',
                'dispatching a web request 5',
            ],
            $this->hooks,
        );
        self::assertSame($request, $this->dispatched[0][0]);
        self::assertSame($request->getBeanFactory(), $this->dispatched[0][1]);
    }

    public function testTheScopesAreReleasedWhenTheTestEnds(): void
    {
        $case = new self('scopes');
        $case->setUp();
        $case->get('hello/ada');
        $case->bean('Router');
        $case->tearDown();

        self::assertSame(
            ['created http 1', 'dispatching a web request 1', 'created http 2', 'released 1', 'released 2'],
            $case->hooks,
        );
    }

    public function testTheErrorLogIsAFileOfTheTestsOwn(): void
    {
        $this->expectLoggedFailure();
        error_log('Something failed.');

        self::assertSame($this->errorLogFile, ini_get('error_log'));
        self::assertStringStartsWith(sys_get_temp_dir() . '/', $this->errorLogFile);
        self::assertStringEndsWith('] Something failed.' . PHP_EOL, $this->errorLog());
    }

    public function testATestFailsWhenTheApplicationWroteToItsLogUnexpectedly(): void
    {
        $case = new self('unexpected');
        $case->setUp();
        error_log('Something failed.');

        try {
            $case->tearDown();
            self::fail('The test passed.');
        } catch (AssertionFailedError $e) {
            self::assertSame(
                'The application wrote to its error log, which the test did not expect (expectLoggedFailure()).'
                . PHP_EOL . 'Failed asserting that two strings are identical.',
                $e->getMessage(),
            );
        }

        self::assertFileDoesNotExist($case->errorLogFile);
        self::assertSame($this->errorLogFile, ini_get('error_log'), 'the log of the test around it again');
        self::assertSame('', $this->errorLog());
    }

    public function testATestThatExpectsAFailureInTheLogPasses(): void
    {
        $case = new self('expected');
        $case->setUp();
        $case->expectLoggedFailure();
        error_log('Something failed.');
        $case->tearDown();

        self::assertFileDoesNotExist($case->errorLogFile);
        self::assertSame($this->errorLogFile, ini_get('error_log'));
    }

    public function testWithoutATemporaryFileForTheErrorLogATestCannotBegin(): void
    {
        $case = new self('no temporary file');
        $case->noTemporaryFile = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageExactly('There is no temporary file for the error log.');

        $case->setUp();
    }

    public function testASubclassMakesAndReleasesScopesOfItsOwn(): void
    {
        $scope = $this->newScope('cli');

        self::assertSame($this->session, $scope->get(SessionServiceInterface::class));
        self::assertSame(['created cli 1'], $this->hooks);

        $this->releaseScopes();
        $this->releaseScopes();

        self::assertSame(['created cli 1', 'released 1'], $this->hooks, 'released once');
    }

    protected function newErrorLogFile(): false|string
    {
        return $this->noTemporaryFile
            ? false
            : parent::newErrorLogFile();
    }

    /**
     * @return list<string>
     */
    protected function configurationFiles(string $transport): array
    {
        $files = parent::configurationFiles($transport);

        return $this->missingFile === null
            ? $files
            : [...$files, $this->missingFile];
    }

    protected function scopeCreated(BeanFactory $scope, string $transport): void
    {
        parent::scopeCreated($scope, $transport);

        $this->hooks[] = 'created ' . $transport . ' ' . $this->numberOf($scope);

        $entityManager = $this->entityManagerOf($scope);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(NoteEntity::class)]);
    }

    protected function scopeReleased(BeanFactory $scope): void
    {
        parent::scopeReleased($scope);

        $this->hooks[] = 'released ' . $this->numberOf($scope);

        $this->entityManagerOf($scope)->getConnection()->close();
    }

    protected function beforeDispatch(TestCliRequest|TestHttpRequest $request, BeanFactory $scope): void
    {
        parent::beforeDispatch($request, $scope);

        $this->hooks[] = 'dispatching ' . ($request instanceof TestHttpRequest ? 'a web request' : 'a command') . ' '
            . $this->numberOf($scope);
        $this->dispatched[] = [$request, $scope];
    }

    private function numberOf(BeanFactory $scope): int
    {
        $position = array_search($scope, $this->scopesSeen, true);

        if ($position === false) {
            $this->scopesSeen[] = $scope;
            $position = count($this->scopesSeen) - 1;
        }

        return $position + 1;
    }

    private function entityManagerOf(BeanFactoryInterface $scope): EntityManagerInterface
    {
        $factory = $scope->get(EntityManagerFactoryInterface::class);
        assert($factory instanceof EntityManagerFactoryInterface);

        return $factory->get();
    }

    private function configurationService(string $transport = 'http'): ConfigurationServiceInterface
    {
        $settings = $this->bean(ConfigurationServiceInterface::class, $transport);
        assert($settings instanceof ConfigurationServiceInterface);

        return $settings;
    }
}
