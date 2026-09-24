# Upgrading

What an application changes when it moves to a newer ampf. The newest change comes first.

## The 2026 restructuring

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
