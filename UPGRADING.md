# Upgrading

What an application changes when it moves to a newer ampf. The newest change comes first.

## Checked configuration, route parameters by name, isolated scopes

The framework checks what it is given and says what is wrong: bean definitions, configuration blocks, route and template names, time values. Every exception it throws now carries a message. Besides, the web router hands a route's captures to `execute()` by their names, configuration files and templates run in scopes of their own, and a command can end with an exit code. The development container (`docker/`) runs every check; applications are not affected by it.

### 1. Route parameters go by name (web)

`HttpRouter::routeBean()` — and so `route()` and `HttpView::subRoute()` — passes a route's named captures to `execute()` as **named arguments**: the capture `(?P<reportId>…)` is the parameter `$reportId`, in any order. Before, they went in the order of the pattern, whatever the parameters' names.

- **Check every web controller's `execute()` against its route's pattern:** a parameter named differently from its capture is renamed (or the capture is). A capture `execute()` has no parameter for is refused: `RuntimeException('The route's parameter <name> names no parameter of <Controller>::execute().')`.
- A variadic `execute(string ...$parameters)` takes every capture, keyed by its name.
- `HttpView::subRoute($bean, $params)` passes `$params` by their keys too: key them by the names of `execute()`'s parameters (a list is refused).
- The command line is unchanged: `CliRouter` passes the arguments in their order.

### 2. Configuration files and templates run in scopes of their own

- **Configuration files** (`ApplicationContext::boot()`): each file runs in a function of its own. It no longer sees the variables of `boot()` — a file that read `$config` (the merge so far) or the variables of an earlier file gets nothing; one that assigned `$config` no longer overwrites the merge. A file that returns no array keyed by strings is refused with its path in the message.
- **Templates** (`AbstractView::render()`): the template's local variables are the view's variables and nothing else, `$this` is the view. `$key` and `$value` are ordinary template variables now (they arrived with the last variable's name and value before), and so are `$path` and `$view`, which held the template's file and name (a view variable `path` was left out for that). A variable whose name is no variable name (`my-title`, `1st`, `this`) is not extracted — read it with `$this->get('my-title')`. A template that throws prints nothing: its output and every output buffer it opened are discarded.

### 3. Bean definitions are checked

A definition is checked when its bean is first created. What was ignored or failed later with a PHP error is now a `RuntimeException` that names the bean: an unknown option (`initmethod`, `propertys`), a `class` that does not exist or is abstract, a `scope` other than `singleton` and `prototype`, `properties` that are no `bean id => setter suffix` map, a setter or an `initMethod` the class does not have (as a public method), a `parent` without a definition, a cycle of parents, and `beans` that are no array.

`BeanFactoryInterface` gained `getConfig(): array` (the bean `Config`, checked to be the configuration): an application's own implementation of the interface adds it.

### 4. The command line's exit code

`CliRequestInterface` gained `getExitCode(): int` and `setExitCode(int): self` (0 to 254); `CliRequest` implements them. End the command line entry point with `exit($request->getExitCode());` after `flush()`, and let a failing controller call `setExitCode(1)` instead of `exit(1)` — the response is printed first. `CliRequest`'s constructor takes the command line as an optional list (`$_SERVER['argv']` when null).

### 5. Stricter where it was loose

- **Redirects:** `setRedirect()` takes a status from 300 to 399 (default 301); another is an `InvalidArgumentException`.
- **The base path** is compared segment by segment: under `/app`, the route of `/application/x` is no longer `lication/x`, and a Referer of `/application` is no local one.
- **`Accept-Language`** follows the RFC's grammar of quality values: `q=1.0`, `q=0.50` and `Q=0.5` count (they were skipped), a weight of more than three decimals does not; `q=0` stays a refusal.
- **`ViewInterface::formatTime(mixed $time, ?string $format = null)`:** the default `null` of `$time` is gone (a call without a time always threw); a `DateTimeImmutable` is taken like a `DateTime`; anything but a `DateTimeInterface` or a Unix timestamp is a `RuntimeException`. `AbstractView::getTimeZoneUTC()` and `$timezoneUtc` are gone (a timestamp is UTC anyway). An application's override of `formatTime()` keeps compatible by removing its own default, or keeping it.
- **`HttpView::getAssetLink()`** resolves `..` as a whole segment only (`img/..logo.png` is a file name now; it dropped `img/` before), and refuses a `..` above the web root with a `RuntimeException` (a plain `Exception` before).
- **`ViewResolver`** refuses a name with a line feed at its end, and a directory as a template; `viewDirectory` must be a directory that exists. Its exceptions are `RuntimeException`s (plain `Exception`s before) — a `catch (Exception)` still catches them.
- **`TranslatorService::setLanguage()`** takes a language code — letters, then parts of letters and digits after `_` or `-` (`de`, `en_GB`, `zh-Hant-TW`) — and refuses anything else (a path above all) with an `InvalidArgumentException`. Setting another language loads its texts (the first language's texts stayed before). `getKey($translation, false)` compares with the case now. The protected `setConfig()` is gone; `loadTexts($directory)` loads a language's texts.
- **`SessionService`:** the session id travels in the cookie only — `session.use_only_cookies` is gone from the configuration (PHP deprecated switching it off). A `session` block that is no array is refused (it was ignored).
- **`FileStringCacheService`:** `defaultttl` must be a number of seconds (text such as `'an hour'` was 0); an entry whose time or text has the wrong type counts as damaged and goes.
- **`ConfigurationService::setConfig()`** refuses a domain whose values are no array.
- **`DoctrineConfig`:** the `doctrine` block must be a non-empty array keyed by names, and `configuration`, `connectionParams`, `typeOverrides` and `mappingOverrides` must be of their types. `getConnectionParams()` is typed as DBAL's connection parameters (it claimed `pdo_mysql` only).
- **`UTCDateTimeType`** writes a `DateTimeImmutable` as its UTC time (it was written as its local wall time); a `DateTime` is switched to UTC in place, as before.

### 6. Removed

- `Functions::mb_str_split()` and `Functions::mb_ucfirst()`: PHP's own `mb_str_split()` and `mb_ucfirst()` (PHP 8.4) do the same.
- `AbstractView::getTimeZoneUTC()`, `$timezoneUtc` and `TranslatorService::setConfig()` (section 5).

### 7. New

- `ampf\Bootstrap\ErrorSettings`: `applyDefaults()` before the configuration is loaded, `apply($config, $projectRoot)` after it, from the new `errors` block of `config/default.php` (`display` false, `log` true, `log-file` null). An application's own copy of it can go.
- `ampf\Controller\Http\AbstractController` and `ampf\Controller\Cli\AbstractController`: a controller's base, with `getRequest()`, `getView()` and empty lifecycle hooks. An application's base controller may extend it.
- `ampf\BeanAccess\Generator\BeanAccessGenerator` and `ampf\Controller\Cli\BeanAccessGeneratorController` (the bean `BeanAccessGeneratorController` of `config/cli.php`): the access traits of an application's interface beans and repositories, generated; `check` fails when one is stale (README, section 4). An application with a generator of its own may replace it — the output is the same shape.
- `ampf\BeanAccess\Doctrine\EntityManagerFactoryAccess`.
- `HttpRequest`'s protected seams `sendHeader()`, `sendStatusCode()` and `removeHeader()`, beside `sendCookie()`: a test request overrides them to record the response's head. `flush()` is marked `@phpstan-impure` (PHPStan forgets what it knew of the request across it); a test double whose `flush()` does nothing marks its override `@phpstan-pure`, or PHPStan reports it.
- `ErrorSettings` and `DoctrineConfiguration` are not final.

### 8. The tools

- `docker/` runs every check in a container (README, section 13); the Composer scripts stay.
- `phpunit.xml.dist` has an `integration` suite besides `unit`, and the coverage counts `src/` only.

ampf's source tree now follows one set of conventions: PascalCase, singular namespaces directly under `src/`, interfaces with the `Interface` suffix and their implementation next to them, access traits under `ampf\BeanAccess`, and the framework's services keyed by their interface. Every class keeps what it did; the names changed, and a few features that applications had to write themselves moved into the framework.

### 1. Requirements

- PHP 8.5 with `ctype`, `json`, `mbstring`, `pdo`, `pdo_mysql` and `session` (now declared in `composer.json`); Doctrine ORM `^3.7`, `symfony/cache` `^8.1`.
- `ampf\` is `src/` (was `src/ampf/`); the tests' namespace `ampfTest\` is gone from the autoloader (the tests are `ampf\Tests\`, development only). `composer update amp-framework/ampf` writes the new autoloader.

### 2. The new names

Every class, interface and trait moved; the rewrite is mechanical — imports, type declarations, `instanceof`, `::class`, and fully qualified names in docblocks and templates' `@var` lines.

| Before | Now |
| --- | --- |
| `ampf\ApplicationContext` | `ampf\Bootstrap\ApplicationContext` |
| `ampf\Functions` | `ampf\Helper\Functions` |
| `ampf\Registry` | `ampf\Helper\Registry` |
| `ampf\beans\BeanFactory` | `ampf\Bean\BeanFactoryInterface` |
| `ampf\beans\BeanFactoryAccess` | `ampf\Bean\BeanFactoryAccessInterface` |
| `ampf\beans\impl\DefaultBeanFactory` | `ampf\Bean\BeanFactory` |
| `ampf\beans\impl\DefaultBeanFactoryAccess` | `ampf\BeanAccess\BeanFactoryAccess` |
| `ampf\beans\access\ConfigurationServiceAccess` | `ampf\BeanAccess\Service\ConfigurationServiceAccess` |
| `ampf\beans\access\DoctrineConfigAccess` | `ampf\BeanAccess\Doctrine\DoctrineConfigAccess` |
| `ampf\beans\access\DoctrineEntityManagerAccess` | `ampf\BeanAccess\Doctrine\DoctrineEntityManagerAccess` |
| `ampf\beans\access\HasherServiceAccess` | `ampf\BeanAccess\Service\HasherServiceAccess` |
| `ampf\beans\access\RouteResolverAccess` | `ampf\BeanAccess\RouteResolverAccess` |
| `ampf\beans\access\SessionServiceAccess` | `ampf\BeanAccess\Service\SessionServiceAccess` |
| `ampf\beans\access\StringCacheServiceAccess` | `ampf\BeanAccess\Service\StringCacheServiceAccess` |
| `ampf\beans\access\TimeL10nServiceAccess` | `ampf\BeanAccess\Service\TimeL10nServiceAccess` |
| `ampf\beans\access\TranslatorServiceAccess` | `ampf\BeanAccess\Service\TranslatorServiceAccess` |
| `ampf\beans\access\ViewResolverAccess` | `ampf\BeanAccess\ViewResolverAccess` |
| `ampf\beans\access\XsrfTokenServiceAccess` | `ampf\BeanAccess\Service\XsrfTokenServiceAccess` |
| `ampf\controller\Controller` | `ampf\Controller\ControllerInterface` |
| `ampf\exceptions\ControllerInterruptedException` | `ampf\Controller\ControllerInterruptedException` |
| `ampf\doctrine\Config` | `ampf\Doctrine\DoctrineConfigInterface` |
| `ampf\doctrine\impl\DefaultConfig` | `ampf\Doctrine\DoctrineConfig` |
| `ampf\doctrine\EntityManagerFactory` | `ampf\Doctrine\EntityManagerFactoryInterface` |
| `ampf\doctrine\impl\DefaultEntityManagerFactory` | `ampf\Doctrine\EntityManagerFactory` |
| `ampf\doctrine\entities\Base` | `ampf\Doctrine\Entity\AbstractEntity` |
| `ampf\doctrine\repositories\Base` | `ampf\Doctrine\Repository\AbstractRepo` |
| `ampf\doctrine\types\UTCDateTimeType` | `ampf\Doctrine\Type\UTCDateTimeType` |
| `ampf\requests\CliRequest` | `ampf\Request\CliRequestInterface` |
| `ampf\requests\HttpRequest` | `ampf\Request\HttpRequestInterface` |
| `ampf\requests\impl\DefaultCli` | `ampf\Request\CliRequest` |
| `ampf\requests\impl\DefaultHttp` | `ampf\Request\HttpRequest` |
| `ampf\router\CliRouter` | `ampf\Router\CliRouterInterface` |
| `ampf\router\HttpRouter` | `ampf\Router\HttpRouterInterface` |
| `ampf\router\RouteResolver` | `ampf\Router\RouteResolverInterface` |
| `ampf\router\impl\DefaultCliRouter` | `ampf\Router\CliRouter` |
| `ampf\router\impl\DefaultHttpRouter` | `ampf\Router\HttpRouter` |
| `ampf\router\impl\DefaultRouteResolver` | `ampf\Router\RouteResolver` |
| `ampf\services\cache\string\StringCacheService` | `ampf\Service\StringCache\StringCacheServiceInterface` |
| `ampf\services\cache\string\impl\FileBased` | `ampf\Service\StringCache\FileStringCacheService` |
| `ampf\services\configuration\ConfigurationService` | `ampf\Service\Configuration\ConfigurationServiceInterface` |
| `ampf\services\configuration\impl\DefaultConfigurationService` | `ampf\Service\Configuration\ConfigurationService` |
| `ampf\services\hasher\HasherService` | `ampf\Service\Hasher\HasherServiceInterface` |
| `ampf\services\hasher\impl\DefaultHasherService` | `ampf\Service\Hasher\HasherService` |
| `ampf\services\session\SessionService` | `ampf\Service\Session\SessionServiceInterface` |
| `ampf\services\session\impl\DefaultSessionService` | `ampf\Service\Session\SessionService` |
| `ampf\services\timel10n\TimeL10nService` | `ampf\Service\TimeL10n\TimeL10nServiceInterface` |
| `ampf\services\timel10n\impl\DefaultTimeL10nService` | `ampf\Service\TimeL10n\TimeL10nService` |
| `ampf\services\translator\TranslatorService` | `ampf\Service\Translator\TranslatorServiceInterface` |
| `ampf\services\translator\impl\DefaultTranslatorService` | `ampf\Service\Translator\TranslatorService` |
| `ampf\services\xsrfToken\XsrfTokenService` | `ampf\Service\XsrfToken\XsrfTokenServiceInterface` |
| `ampf\services\xsrfToken\impl\DefaultXsrfTokenService` | `ampf\Service\XsrfToken\XsrfTokenService` |
| `ampf\views\AbstractView` | `ampf\View\AbstractView` |
| `ampf\views\CliView` | `ampf\View\CliViewInterface` |
| `ampf\views\HttpView` | `ampf\View\HttpViewInterface` |
| `ampf\views\View` | `ampf\View\ViewInterface` |
| `ampf\views\ViewResolver` | `ampf\View\ViewResolverInterface` |
| `ampf\views\impl\DefaultCliView` | `ampf\View\CliView` |
| `ampf\views\impl\DefaultHttpView` | `ampf\View\HttpView` |
| `ampf\views\impl\DefaultViewResolver` | `ampf\View\ViewResolver` |

New: `ampf\Bootstrap\DoctrineConfiguration`, `ampf\BeanAccess\AbstractAccess`, `ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess` (section 5).

Watch the names that now mean something else: `ampf\Request\HttpRequest` is the **class** (the interface is `HttpRequestInterface`), and likewise `CliRequest`, `HttpRouter`, `CliRouter`, `RouteResolver`, `HttpView`, `CliView`, `ViewResolver`, `BeanFactory` and the services. `ampf\BeanAccess\BeanFactoryAccess` is the **trait** (the interface is `ampf\Bean\BeanFactoryAccessInterface`). A class of the application with the same short name as a framework class it extends imports it under an alias (`use ampf\Doctrine\Repository\AbstractRepo as AmpfAbstractRepo;`).

### 3. The bean ids

The framework's services are keyed by their interface. **An override under an old id is silently ignored** — the framework asks for the interface — so check every `beans` entry, every `->get('…')`, `->set('…')` and `->has('…')` of the application and its tests:

| Before | Now |
| --- | --- |
| `'ConfigurationService'` | `ConfigurationServiceInterface::class` |
| `'DoctrineConfig'` | `DoctrineConfigInterface::class` |
| `'EntityManagerFactory'` | `EntityManagerFactoryInterface::class` |
| `'HasherService'` | `HasherServiceInterface::class` |
| `'RouteResolver'` | `RouteResolverInterface::class` (now in `config/default.php` for both transports) |
| `'SessionService'` | `SessionServiceInterface::class` |
| `'StringCacheService'` | `StringCacheServiceInterface::class` |
| `'TimeL10nService'` | `TimeL10nServiceInterface::class` |
| `'TranslatorService'` | `TranslatorServiceInterface::class` |
| `'ViewResolver'` | `ViewResolverInterface::class` |
| `'XsrfTokenService'` | `XsrfTokenServiceInterface::class` |

`'BeanFactory'`, `'Config'`, `'Router'`, `'Request'`, `'RequestStub'` and `'View'` keep their names.

### 4. What else changed

- **Interfaces gained methods.** `HttpRequestInterface`: `comesFromThisSite()`, `getBody()`, `getGetString()`, `getPostString()`, `getParamString()`, `getParamStrings()`, `getPostStringMap()`. `SessionServiceInterface`: `regenerateId()`, `renew()`. `HttpViewInterface`: `getParamString()`. A class that implements one of them itself (a test double, say) adds them; a subclass of the framework's class inherits them — and **must delete a copy of its own** with an incompatible signature or a `private` property of the same name (`HttpRequest` has a `protected ?string $body`).
- **`EntityManagerFactoryInterface::get()` returns `EntityManagerInterface`**, never null: it creates the entity manager at its first call when `init()` did not. An `assert($em instanceof EntityManagerInterface)` after it is now redundant (PHPStan says so).
- **`AbstractRepo` has typed readers:** `entityList()`, `entityOrNull()`, `intResult()`, `intExecute()` (protected). A repository base class of the application with methods of these names deletes them; `entityOrNull()` is declared `?AbstractEntity`.
- **Link parameters may be any scalar** (`HttpRequest::getActionLink()`, `setRedirect()`, `HttpView::getActionLink()`): an int id needs no cast; null is an empty value. An override that only stringified them can go.
- **`HttpView::getParam()` (protected, untyped) is gone**; `getParamString()` (public, a string) replaces it.
- **`HttpRequest::flush()` removes PHP's `X-Powered-By` header.**
- **The string cache can be switched off:** `stringfilecache.enabled` (default true); off, nothing is stored or served. A subclass that added such a switch can go.
- **The Doctrine default** of `config/default.php` is built by `DoctrineConfiguration::create([])` (native lazy objects) instead of the deprecated `ORMSetup::createAttributeMetadataConfiguration()`.

### 5. What moved into the framework

- `ampf\BeanAccess\AbstractAccess` — the `abstract public function getBeanFactory()` every access trait declares; an application's traits `use` it (see `README.md`, section 4).
- `ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess` — `getDoctrineEntityRepository(Entity::class, Repo::class)`, which registers a repository as the bean `'Doctrine.Repository.' . Entity::class` at its first use.
- `ampf\Bootstrap\DoctrineConfiguration::create(array $entityPaths, ?string $cacheDirectory = null)` — the ORM configuration: the attribute mapping, native lazy objects, and with a cache directory the mapping, queries and results kept as PHP files there.
- The typed request readers, `getBody()`, `comesFromThisSite()`, the session's `regenerateId()` and `renew()`, the string cache's switch and the repository's typed readers (section 4).

### 6. The tools

- PHP-CS-Fixer is a development dependency (`vendor/bin/php-cs-fixer`, 3.95); `tools/php-cs-fixer/` is gone.
- `phpunit.xml.dist` exists; the tests run with `composer test` (or `vendor/bin/phpunit`).
- `phpcs.xml.dist` — the standard an application extends — no longer runs `SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation` (PHP-CS-Fixer imports the classes annotations name) nor the deprecated `SlevomatCodingStandard.TypeHints.UnionTypeHintFormat` (`DNFTypeHintFormat` replaces it), and excludes `tests/Unit/*` and `tests/Integration/*` from `JumpStatementsSpacing` (consecutive PHPUnit provider yields). An application's own overrides of these become redundant.
