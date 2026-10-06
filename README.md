# ampf — the ampf MVC PHP framework

ampf is a small PHP MVC framework built around one idea: an explicitly configured, lazy dependency container — the **bean factory** — that does no magic. Configuration is PHP files returning arrays; routes map to controller beans; controllers prepare a response on the request object; PHP templates render through view objects. Nothing is discovered, autowired or scanned: every bean, route and directory is named in configuration.

Because every object is created on its first use, an application opens no database connection unless a request uses the database, and starts no PHP session unless a request uses the session.

ampf requires PHP 8.5 with `ctype`, `json`, `mbstring`, `pdo`, `pdo_mysql` and `session`; it uses Doctrine ORM 3 and `symfony/cache`. Its API may change between revisions — [`UPGRADING.md`](UPGRADING.md) lists what an application has to change.

```sh
composer require amp-framework/ampf:dev-master
```

## 1. Boot

An entry point does five things:

```php
use ampf\Bean\BeanFactory;
use ampf\Bootstrap\ApplicationContext;
use ampf\Bootstrap\ErrorSettings;
use ampf\Bootstrap\TraceSettings;
use ampf\Request\HttpRequestInterface;
use ampf\Router\HttpRouterInterface;

require __DIR__ . '/../vendor/autoload.php';

// 1. runtime options: every error reported and logged, none displayed, until the configuration says otherwise,
//    and the arguments of the calls in a stack trace (what a log needs to debug a failure of production)
ErrorSettings::applyDefaults();
TraceSettings::apply();
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// 2. the configuration: an ordered list of PHP files, each returning an array, merged into one
$config = ApplicationContext::boot([
    __DIR__ . '/../vendor/amp-framework/ampf/config/default.php', // the framework's defaults
    __DIR__ . '/../vendor/amp-framework/ampf/config/http.php',    // the framework's web beans
    __DIR__ . '/../config/default.php',                           // the application's beans and settings
    __DIR__ . '/../config/http.php',                              // the application's controllers and routes
    __DIR__ . '/../config/local.php',                             // machine settings (database, keys): not in Git
]);
ErrorSettings::apply($config, dirname(__DIR__));                  // the `errors` block

// 3. the bean factory over it
$beanFactory = new BeanFactory($config);

// 4. route the request
$router = $beanFactory->get('Router');
assert($router instanceof HttpRouterInterface);
$request = $beanFactory->get('Request');
assert($request instanceof HttpRequestInterface);
$router->route($request);

// 5. send the status code, the headers and the body (or the redirect)
$request->flush();
```

A command line entry point loads `config/cli.php` (the framework's and the application's) in place of `http.php`, asserts `CliRouterInterface`/`CliRequestInterface`, and ends with `exit($request->getExitCode());` after the flush (section 8). Every listed file must exist (`require`, no silent skip). The files are executable PHP, each run in a scope of its own: its variables stay its own, and it cannot see or change what the files before it returned. A file that returns no array keyed by strings is refused with its name. Never boot the real application to obtain a test's dependencies; build a test configuration instead.

`TraceSettings::apply()`, called right after `ErrorSettings::applyDefaults()`, keeps the arguments of the calls in a stack trace (`zend.exception_ignore_args` off: it is on in `php.ini-production`, off in `php.ini-development` and without a php.ini), so that a failure of production can be debugged from its log. What must not be there is a parameter marked `#[SensitiveParameter]` (a password, a key): PHP lists it as a `SensitiveParameterValue` whatever the setting says, and nothing else is hidden. `ErrorSettings::apply()` follows the configuration's `errors` block: `display` (false: errors never reach a response — true only on a development machine), `log` (true) and `log-file` (a path relative to the project root, or absolute; null keeps PHP's own log). A setting of another type is refused.

## 2. Configuration merge — one level deep

`ApplicationContext::boot()` merges **only one level below the top-level keys**. A later file wins, but:

| Entry | Effect of a later definition |
| --- | --- |
| a scalar (`viewDirectory`, `translation.dir`) | replaced |
| `beans`, `routes` | the maps are combined by key; a later definition of one bean or route **replaces that entry's whole options array** |
| `doctrine`, `stringfilecache`, `cookies`, `session`, … | combined by key; a nested array (`connectionParams`, `session.cookie`) is replaced as a whole |
| `configuration.service` | combined by domain; a later `'.myapp' => [...]` **replaces the whole domain** — repeat every key the domain needs |

Consequences: overriding the `View` bean needs both `class` and `scope` (supplying only one loses the other); an empty `'routes' => []` erases nothing; existing keys keep their position and new keys append, so a route added in a later file lands **after** an existing catch-all — keep fallback routes last. A top-level array that two files define must be keyed by strings (a list cannot be merged: `boot()` throws), which is why the framework's `config/default.php` names no block that only one optional service reads (`assets`): the service applies its defaults in code, and the application's block stands as it is written. The whole merged array is the bean `Config`. Top-level keys with dots (`translation.dir`) are literal keys, not paths.

## 3. Beans

A bean id is an explicit string. A **service** is keyed by its interface (`SessionServiceInterface::class`), and an application replaces the framework's implementation by configuring another class under the same key. The four **roles** the two transports fill with classes of their own are keyed by name: `Router`, `Request`, `RequestStub` and `View`. Two beans exist in every factory: `BeanFactory` (the factory itself) and `Config` (the merged configuration).

```php
return [
    'beans' => [
        ReportServiceInterface::class => ['class' => ReportService::class],
        SessionServiceInterface::class => ['class' => MySessionService::class], // replaces the framework's
        'ReportController' => ['class' => ReportController::class],             // controllers: named by the routes
        'View' => ['class' => MyHttpView::class, 'scope' => 'prototype'],
    ],
];
```

| Option | Meaning |
| --- | --- |
| `class` | instantiated with `new $class()` — **no constructor arguments, no autowiring** |
| `scope` | `singleton` (default: cached in this factory instance) or `prototype` (a new object for every lookup) |
| `properties` | map of **dependency bean id → setter suffix**: `['Config' => 'config']` resolves the `Config` bean and calls `setConfig($config)` (a missing setter throws) |
| `initMethod` | a method without arguments, called after the properties are set |
| `parent` | another bean's definition applied to the object first — configuration reuse, not inheritance; the child's `class` is instantiated |

A definition is checked when its bean is first created, and refused with a message that names the bean: an option the factory does not know (a typo such as `initmethod`), a class that does not exist or cannot be instantiated, a scope other than the two, properties that are not `bean id => setter suffix`, a missing setter or init method, a parent without a definition and a cycle of parents.

Initialisation order: parent configuration → property injection → `initMethod` → scope caching. Any object implementing `ampf\Bean\BeanFactoryAccessInterface` receives the factory itself during property injection (`setBeanFactory()`), which is how the framework's and an application's classes reach their dependencies. Because caching happens *after* initialisation, an eager dependency cycle recurses — resolve dependencies lazily (section 4).

`getConfig()` is the merged configuration (the bean `Config`), the array a class reads its block from. `get($id, $creator)` accepts a fallback callable for ids without a definition (the repositories, section 9); a configured definition wins over the callable; an unknown id without a callable throws `No configuration for bean …`. `set($id, $object)` puts an object in place of a bean by hand (tests use it for doubles) and wins over the configuration. A singleton belongs to one factory instance — there is no cross-request cache; a long-running CLI keeps its singletons alive.

## 4. Access traits — lazy, typed dependencies

Instead of constructor injection, a class implements `BeanFactoryAccessInterface`, uses the `ampf\BeanAccess\BeanFactoryAccess` trait (it stores the factory) and one small trait per dependency. The framework ships one for each of its services:

| Trait | Getter |
| --- | --- |
| `ampf\BeanAccess\RouteResolverAccess` | `getRouteResolver(): RouteResolverInterface` |
| `ampf\BeanAccess\ViewResolverAccess` | `getViewResolver(): ViewResolverInterface` |
| `ampf\BeanAccess\Service\AssetServiceAccess` | `getAssetService()` |
| `ampf\BeanAccess\Service\ConfigurationServiceAccess` | `getConfigurationService()` |
| `ampf\BeanAccess\Service\HasherServiceAccess` | `getHasherService()` |
| `ampf\BeanAccess\Service\SessionServiceAccess` | `getSessionService()` |
| `ampf\BeanAccess\Service\StringCacheServiceAccess` | `getStringCacheService()` |
| `ampf\BeanAccess\Service\TimeL10nServiceAccess` | `getTimeL10nService()` |
| `ampf\BeanAccess\Service\TranslatorServiceAccess` | `getTranslatorService()` |
| `ampf\BeanAccess\Service\XsrfTokenServiceAccess` | `getXsrfTokenService()` |
| `ampf\BeanAccess\Doctrine\DoctrineConfigAccess` | `getDoctrineConfig()` |
| `ampf\BeanAccess\Doctrine\EntityManagerFactoryAccess` | `getEntityManagerFactory()` |
| `ampf\BeanAccess\Doctrine\DoctrineEntityManagerAccess` | `getDoctrineEntityManager(): EntityManagerInterface` |
| `ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess` | the base of a repository's trait (section 9) |

An application writes the same shape for its own beans — `use AbstractAccess;` declares the `getBeanFactory()` the host provides:

```php
trait ReportServiceAccess
{
    use AbstractAccess;

    protected ?ReportServiceInterface $__reportService = null;

    public function getReportService(): ReportServiceInterface
    {
        if ($this->__reportService === null) {
            $object = $this->getBeanFactory()->get(ReportServiceInterface::class);
            assert($object instanceof ReportServiceInterface);
            $this->setReportService($object);
        }

        assert($this->__reportService instanceof ReportServiceInterface);

        return $this->__reportService;
    }

    public function setReportService(ReportServiceInterface $object): void
    {
        $this->__reportService = $object;
    }
}
```

The trait of `…\Service\Report\ReportServiceInterface` is `…\BeanAccess\Service\ReportServiceAccess`: one namespace level above the interface's own (a type under `Doctrine\` keeps its level). The setter lets a test hand in a double without a bean factory. The `assert()` states the expected type; it is no validation of hostile input and can be disabled by runtime configuration. A trait caches what it fetched for the lifetime of its host — a controller's getter of a *prototype* bean hands out the same object twice.

### Generated traits

Every name of a trait is derived, so a generator writes them: `ampf\BeanAccess\Generator\BeanAccessGenerator` renders one for every singleton bean keyed by one of the application's interfaces, and one for every entity whose `#[Entity]` names a repository (with `AbstractRepoAccess`, section 9) — the framework's own traits are its output. The command line controller `BeanAccessGeneratorController` (the bean of `config/cli.php`) runs it; the application routes a command to it and names itself in the `beanAccessGenerator` block, the generator's arguments by name:

```php
// config/cli.php of the application
return [
    'routes' => [
        'beanAccess/generate' => ['pattern' => 'beanAccess/generate', 'controller' => 'BeanAccessGeneratorController'],
    ],
    'beanAccessGenerator' => [
        'projectRoot' => dirname(__DIR__),
        'namespace' => 'App',             // the application's namespace, in src/ (PSR-4)
        'handWritten' => ['Request/'],    // traits under src/BeanAccess/ written by hand: a file, or a directory
        // optional: 'sourceDirectory' => 'src', 'accessNamespace' => 'BeanAccess',
        //           'entityNamespace' => 'Doctrine\Entity' (null: none), 'lineLength' => 120 (PHPCS's)
    ],
];
```

`php bin/index.php beanAccess/generate` writes the traits that are new or changed; with the argument `check` it writes nothing, lists them, and exits with 1 when there is one — a CI step. A trait nothing generates any more is listed as stale in both modes (and fails the check), for a person to delete; prototype beans get no trait.

## 5. Routes and the controller lifecycle

A route is an id mapped to a regular-expression **pattern** and a **controller bean id**:

```php
'routes' => [
    'report/view' => [
        'pattern' => 'report/(?P<reportId>[1-9][0-9]*)',
        'controller' => 'ReportController',
    ],
],
```

`RouteResolver` anchors the pattern to the whole route (`/^…$/D`: `$` never matches before a final line feed), takes the **first** match in configuration order, extracts the named captures as parameters, and builds links the other way round by substituting the named-capture expressions with the parameters, percent-encoded (`getRoutePatternByRouteID()`; parameters the pattern does not name become the query string). Patterns omit the leading slash; the homepage's pattern is the empty string; a catch-all is `(?P<pathInfo>.*)`, last.

`HttpRouter::route()` asks the request for its controller bean, checks that the bean exists and implements `ampf\Controller\ControllerInterface` (a message names what is wrong), then runs:

```text
beforeAction() → execute(...routeParameters) → afterAction()
```

`ControllerInterface` declares `beforeAction(): void`, `afterAction(): void`, `execute(): void` and `setRequest(CliRequestInterface|HttpRequestInterface $request): void`; a concrete `execute()` adds the parameters its route captures, **by name**: the capture `reportId` is the parameter `$reportId`, whatever the order, and each is optional (`?string $reportId = null`) for the interface's zero-argument signature. A capture `execute()` has no parameter for is refused with a message that names both; a variadic `execute(string ...$parameters)` takes them all by their names. Throwing `ampf\Controller\ControllerInterruptedException` stops the lifecycle (the router catches it): a login guard sets a redirect and throws, and neither `execute()` nor `afterAction()` runs. Setting a redirect alone does not stop execution — return or throw.

`ampf\Controller\Http\AbstractController` and `ampf\Controller\Cli\AbstractController` are a controller's base: lifecycle hooks that do nothing, and `getRequest()` and `getView()` — the beans `Request` and `View`, fetched at their first use and checked to be of the transport (a web controller refuses a command line request, and the other way round). A command has a base of its own on top of the command line's (section 8).

Routes enforce nothing: the HTTP method, authentication, ownership and token checks are the controller's job. Wrapping the response in a layout is an application convention (typically in the `afterAction()` of a base controller), not framework behaviour.

## 6. The HTTP request

`ampf\Request\HttpRequest` implements `HttpRequestInterface` (the `Request` bean, a singleton; `RequestStub` is a prototype configured like it, for sub-requests). It reads `$_GET`, `$_POST`, `$_COOKIE`, `$_SERVER` and `$_FILES` once, in its constructor (scalars as strings; arrays kept), derives the route from `REQUEST_URI` minus the script's directory and the query string, and collects the response until `flush()`.

- **Input.** `hasGetParam()`/`getGetParam()`, `hasPostParam()`/`getPostParam()`, `getCookieParam()`, `getServerParam()` return what PHP received — a form field can be a string **or an array** (`name[]=`). The typed readers give each call site the shape it expects and treat the other one as absent: `getGetString()`/`getPostString()` (`''` when absent or an array), `getParamString()` (the form first, then the query), `getParamStrings()` (`name[]=` as a list of strings; a single value is a one-item list), `getPostStringMap()` (`name[key]=` with its string values). `getBody()` is the raw body (an API client's JSON), read once. `isPostRequest()`; `getAcceptedLanguages()` parses `Accept-Language` by the RFC's quality values (`q=0` is a refusal; a malformed value counts as none); `getRefererLocalized()` is the Referer relative to this application — only a Referer whose http(s) origin is the request's own host counts; any other, and a request without `HTTP_HOST`, gives null. `comesFromThisSite()` is false when the browser says the request came from another site (`Sec-Fetch-Site: cross-site` or `same-site`): such a request may show a page, but should change nothing on the user's behalf. `getUploadedFiles(key)` gives the files of a form field in the order they came — one for a single input, each of `name[]`, every one below a field of nested names (`a[b][]`) — as `UploadedFile`s: `clientName` (what the browser sent: text, never a path), `size`, `temporaryPath`, `error` (PHP's `UPLOAD_ERR_*`), `isOk()`, `isUploadedFile()` and `moveTo(target)`. An input left empty is none, a file PHP refused comes with its error, a field of another shape than PHP gives is none; there is no type, since the client's is a claim: look at the content. No application touches `$_FILES`. `isPostTooLarge()` says whether the body is bigger than PHP's `post_max_size` — the length the client announced against the limit, which the protected `postMaxSize()` reads; 0 is no limit —: PHP then drops the form and the files, and a request that finds none could not otherwise tell a form that was sent empty from one that was too big. A test's request overrides the protected `createUploadedFile()` to hand out an `UploadedFile` whose protected `isUploaded()` and `moveUploaded()` stand for PHP's `is_uploaded_file()` and `move_uploaded_file()`, which the command line has no upload for.
- **Output.** `setResponse(string)`, `getResponse()`, `setStatusCode(int)` (100 to 599), `addHeader()` (refuses a name that is no token and a value with a control character), `removeHeader()` (drops every line of the name added so far, the defaults every response starts with included, and takes the header out of what PHP holds for the response at `flush()` too — the session's cache limiter adds a `Pragma: no-cache` of its own —, unless a header of the name is added afterwards; refuses a name that is no token), `setRedirect(routeID, params, code, addToken, hashParam)` (a code from 300 to 399, 301 by default — pass 303 after a POST; a redirect and a body exclude each other; a target with a control character is refused), `isRedirect()`, `dropRedirect()` (takes back a redirect already set, so that a body can follow). Every response gets `Content-Type: text/html; charset=UTF-8` and headers that forbid caching unless the controller replaces or removes them (a response that may be kept removes `Pragma` and `Expires`, and adds its own `Cache-Control`). `flush()` checks every header before the first byte goes out and leaves PHP's own `X-Powered-By` out; the status, the headers and the cookies leave through the protected `sendStatusCode()`, `sendHeader()`, `withdrawHeader()` and `sendCookie()`, the seams a test request overrides to record them.
- **Links.** `getActionLink(routeID, params, addToken, hashParam)` builds a URL from a route id: the application's base path (the directory of `SCRIPT_NAME`; a route or a Referer is below it only segment by segment — `/application` is not under `/app`) first, the route's own parameters `rawurlencode()`d into the path, the others as the query string. A parameter may be any scalar — an entity's id is an int —, null is an empty value. `getLink(relative)` prefixes a relative path with the base path.
- **Tokens.** `hasCorrectToken()` checks the **query parameter** named by `XsrfTokenServiceInterface::getTokenIDForRequest()` (`stkn`), and a valid token is consumed: one-time, 32 hex characters (128 random bits), compared with `hash_equals()`, from a session-backed queue of the last 15. A hidden POST field does not satisfy it: generate the form's URL with `addToken: true` and check the token before every change. Tokens do not replace ownership checks.
- **Cookies.** `setCookieParam(key, value, expires = 0, options = [])` gives a cookie the attributes of the configuration's `cookies` block — path `/`, HttpOnly, SameSite=Lax, and Secure where `secure` is null exactly when the request came over https — which a call may change (`['httponly' => false]` for a cookie the page's script reads); `destroyCookieParam(key, options = [])` deletes one with the same attributes. Every cookie leaves through the protected `sendCookie()`, the seam a test request overrides to record them.

## 7. Views and templates

`ampf\View\AbstractView` holds a template's variables (`set()`, `get()`, `has()`, `reset()`); `render($template)` resolves the file under `viewDirectory` (`ViewResolver`: letters, digits, `_`, `.` and `-` per path segment, never `..`, and a file that exists), runs it under output buffering and returns what it printed. The template runs in a scope of its own: its local variables are the view's variables — any name, `$key` and `$value` included; one that cannot be a variable (`my-title`, `this`) is left out — and `$this` is the view. A template that fails prints nothing: its output and any buffer it opened are discarded before the exception goes on. A variable that is `null` counts as absent (`has()` is `isset()`).

The HTML view `ampf\View\HttpView` adds:

- `escape(mixed)` — `htmlspecialchars` with `ENT_QUOTES | ENT_HTML5`; throws on a non-scalar, including `null` — normalise first.
- `getActionLink(routeID, params, addToken)` and `getAssetLink(relative)` (resolves the segments `.` and `..` — a `..` that would leave the web root is refused — and prefixes the base path); `getParamString(name)` — a submitted value as text, what a form shown again puts back into its fields.
- `t(key, args)` — the translator (`vsprintf` over the text; an argument the text has no placeholder for is dropped without a word). `te(key, args)` is `t()` with every argument escaped first — the one to use when an argument is a user's text.
- `formatNumber(number, decimals, decPoint, thousandsSep)` (defaults `.` and a space — applications override), `formatTime(time, format)` (a `DateTimeInterface` or a UNIX timestamp, shown in the default time zone, default `d.m.Y H:i`; anything else is refused).
- `subRender(template, params)` — renders a partial in a **new** `View` bean with only the passed keys (nothing of the parent's variables is copied; keep `View` a prototype).
- `subRoute(controllerBean, params)` — runs a controller through a `RequestStub` and returns its output: a sub-request, not a partial. Prefer `subRender()` for shared markup.

HTML escaping is not JavaScript or JSON encoding; use the right encoding for each context. Keep validation, queries and calculation out of templates.

## 8. The command line

`ampf\Request\CliRequest` takes `argv[1]` as the route (`*` when absent) and passes the remaining arguments **positionally** to `execute()` — there is no named-capture extraction on the command line. `CliRouter` runs the same lifecycle; `CliView` renders text templates (`escape()` changes nothing); `flush()` prints the response. A controller that fails sets the process's exit code on the request (`setExitCode(1)`; 0 to 254), and the entry point ends with `exit($request->getExitCode())` — so a failed command fails a script or a cron job that runs it. `php bin/index.php <route> [arguments…]` is the usual invocation.

## 9. Doctrine

The framework registers two beans: `DoctrineConfigInterface` (`DoctrineConfig`, which reads the merged `doctrine` block) and `EntityManagerFactoryInterface` (`EntityManagerFactory`), whose `get()` returns the one entity manager of the bean factory, created at the first call. Under `doctrine`:

| Key | Purpose |
| --- | --- |
| `configuration` | the ORM `Configuration` object — build it with `ampf\Bootstrap\DoctrineConfiguration::create([entity directories], cache directory)`: the attribute mapping, entities as PHP's native lazy objects (no proxy classes), and in production the mapping, the parsed queries and the results kept as PHP files in the cache directory (empty it on every deploy); without a cache directory (development) nothing is kept |
| `connectionParams` | the DBAL connection array |
| `typeOverrides` | DBAL type replacements; the framework maps `datetime`/`datetimetz` to its `UTCDateTimeType` (datetimes are stored in UTC — a `DateTime` is switched to UTC when it is written, a `DateTimeImmutable` written as its UTC time — and read as UTC) |
| `mappingOverrides` | database type → DBAL type mappings registered on the platform (the framework's default maps `enum` to `string`; an application may set `[]`) |

Base classes: `ampf\Doctrine\Entity\AbstractEntity` (empty) and `ampf\Doctrine\Repository\AbstractRepo`, an `EntityRepository` with `create()` (`new` + `persist()`), `findAllCount()`, `bulkRemoveBy(criteria)` (flushing every 20 removals), `is()`, and typed readers for query results — `entityList(query)`, `entityOrNull(query)`, `intResult(query)`, `intExecute(query)` —, since the ORM's `getResult()` is `mixed` to static analysis. Repositories are created by Doctrine from the entity's `repositoryClass`, never as plain beans: a repository's access trait uses `AbstractRepoAccess`, whose `getDoctrineEntityRepository(Entity::class, Repo::class)` registers the repository in the bean factory under `'Doctrine.Repository.' . Entity::class` at its first use. Nothing in the framework flushes for you; services decide the transaction boundaries. Schema management is the application's responsibility.

## 10. The services the framework preconfigures

| Bean id | Class | Notes |
| --- | --- | --- |
| `SessionServiceInterface` | `SessionService` | starts at its first use, after `session_set_cookie_params()` from the `session.cookie` block (HttpOnly, SameSite=Lax, Secure by the request's scheme), strict mode from `session`, and the id in the cookie only; `get/set/has/removeAttribute()`, `regenerateId()` (a new id for the same data: at a login), `renew()` (an empty session under a new id that stays open: at a logout, so its message is kept), `destroy()` (deletes the cookie with the same attributes), `close()` (writes the session and releases its lock; later writes are not kept). With `session.lazy` on (off by default), `getAttribute()` and `hasAttribute()` answer null and false — without starting a session — for a request without a plausible session cookie (the one `session.name` names, in the bean `Request`; its value must be one a session id can be: 22 to 256 of the characters `A-Z`, `a-z`, `0-9`, `,` and `-`) when none was started in the request: a visitor's page, and one with a forged cookie, leaves no session file behind, and only a write (or `removeAttribute()`, `regenerateId()`, …) starts one |
| `XsrfTokenServiceInterface` | `XsrfTokenService` | one-time tokens of 32 hex characters (`random_bytes(16)`) in a session-backed queue of 15, refused unless they have that shape; `getNewToken()` (one per request), `isCorrectToken()`, the request parameter name `stkn` |
| `TranslatorServiceInterface` | `TranslatorService` | loads `translation.dir/<language>.php` (a `string => string` array; anything else throws) once `setLanguage()` was called — a language is a code such as `de` or `en_GB`, never a path, and another language loads its own texts; an unknown key translates to itself |
| `AssetServiceInterface` | `AssetService` | the version of the files of `assets.directory`, which the web server serves (below): `link($request, 'css/app.css')` is the address with the first 16 hex characters of the SHA-256 of the content as the query parameter `v` |
| `ConfigurationServiceInterface` | `ConfigurationService` | reads `configuration.service`: `setDomain('.app.de_DE')`, then `get('key')` walks the domain up (`.app.de_DE` → `.app`) until a domain defines the key; `null` otherwise. Not the same thing as the `Config` bean |
| `StringCacheServiceInterface` | `FileStringCacheService` | a file cache of strings under `stringfilecache.cachedir` (a directory the process can write to) with `defaultttl` (seconds; whole rendered pages, say); writes are atomic (a temporary file, then `rename()`), one write in a hundred sweeps the expired entries, `sweep()` does it on demand (a cron job); `enabled: false` switches it off — nothing is stored or served |
| `HasherServiceInterface` | `HasherService` | `hash()` (bcrypt at cost 12; refuses a NUL byte; the passwords it is handed are `#[SensitiveParameter]`), `check()` (false, after a dummy verification, for a blank string and for a stored value that is no bcrypt hash), `needsRehash()`, `avoidTimingAttack()` |
| `TimeL10nServiceInterface` | `TimeL10nService` | UTC datetime ↔ UNIX time |
| `ViewResolverInterface` | `ViewResolver` | template path resolution under `viewDirectory` |
| `RouteResolverInterface` | `RouteResolver` | the routes, both directions (section 5) |

### Assets

Stylesheets, scripts and images are files below the document root (`public/assets`) that the web server serves itself, with its own caching; PHP is not in the way. What the framework does is give each file's address a version that changes when the file does, so that a browser may keep the file for a year and fetch the new one the day it changes: `AssetService::link($request, 'css/app.css')` is `/assets/css/app.css?v=3f9a1c0d2b7e4a58` — the application's base path, the address the web server serves the folder under, the path, and the first 16 hex characters of the SHA-256 of the content (the same on every machine, whatever the modification time). A file is read and hashed once for a request.

```php
// config/default.php: the folder the web server serves, and — when it is not `assets` — the address it serves it under
'assets' => ['directory' => dirname(__DIR__) . '/public/assets'],
```

```php
// In a template or a view: <link rel="stylesheet" href="<?= $this->escape($assets->link($request, 'css/app.css')); ?>">
$assets = $this->getAssetService();
```

A file that is not there is a `RuntimeException`, so that a link to a file that was never deployed is found at once, and so is a lookup before the application has named the folder.

## 11. The configuration keys

| Key | Read by | Default |
| --- | --- | --- |
| `beans`, `routes` | the bean factory, `RouteResolver` | sections 3 and 5 |
| `viewDirectory` | `ViewResolver` | `null` — the application names its templates' directory |
| `translation.dir` | `TranslatorService` | `null` — the application names its translations' directory |
| `doctrine` | `DoctrineConfig` | section 9 |
| `cookies` | `HttpRequest::setCookieParam()` | path `/`, domain `''`, `secure` null (by the request's scheme), HttpOnly, SameSite=Lax |
| `session` | `SessionService` | `cookie` (lifetime 0 and the attributes above), `use_strict_mode` true, `lazy` false (on: a read without a session cookie starts no session) |
| `stringfilecache` | `FileStringCacheService` | `cachedir` null (the application names one before it uses the cache), `defaultttl` null (an hour), `enabled` true |
| `configuration.service` | `ConfigurationService` | `'.ampf' => []` |
| `assets` | `AssetService` | not in `config/default.php`; a key the application leaves out has the default of the service: `directory` null (the application names the folder before anything is looked up), `path` `assets` (the address the web server serves the folder under) |
| `errors` | `ErrorSettings::apply()` | `display` false, `log` true, `log-file` null (PHP's own log) |
| `beanAccessGenerator` | `BeanAccessGeneratorController` | none — the application names itself (section 4) |

## 12. The source tree

`ampf\` is `src/` (PSR-4); a namespace is singular and PascalCase, an interface carries the `Interface` suffix and its default implementation sits next to it.

| Namespace | Contents |
| --- | --- |
| `ampf\Bootstrap` | `ApplicationContext` (the configuration), `ErrorSettings` (PHP's error handling), `TraceSettings` (the arguments of the calls in a stack trace, but for what is marked `#[SensitiveParameter]`), `DoctrineConfiguration` (the ORM configuration) |
| `ampf\Bean` | the bean factory and its interfaces |
| `ampf\BeanAccess` | the access traits, and `Generator\BeanAccessGenerator` (section 4) |
| `ampf\Controller` | `ControllerInterface`, `ControllerInterruptedException`; `Http\AbstractController` and `Cli\AbstractController` (the bases), `Cli\BeanAccessGeneratorController` |
| `ampf\Request` | the HTTP and the command line request, and `UploadedFile` (a file of a form's upload, section 6) |
| `ampf\Router` | the two routers and the route resolver |
| `ampf\View` | the views and the template resolver |
| `ampf\Service` | one namespace per service: `Asset`, `Configuration`, `Hasher`, `Session`, `StringCache`, `TimeL10n`, `Translator`, `XsrfToken` |
| `ampf\Testing` | an application's test support (section 13): `ApplicationTestCase`, the doubles, `ExpectsExactMessage`, and the guards of `Guard\` |
| `ampf\Doctrine` | the entity manager factory, `Entity\AbstractEntity`, `Repository\AbstractRepo`, `Type\UTCDateTimeType` |
| `ampf\Helper` | `Functions` (type checks), `Registry` |

Every exception the framework throws says in a sentence what is wrong and names what it concerns — the bean, the route, the file, the configuration key.

## 13. Testing an application

`ampf\Testing` is what an application's tests use from `vendor/`: doubles of the request and the services, a test case that runs the application whole, and guards over the framework's rules. It builds on PHPUnit, a development dependency of ampf and of the application (`composer.json` suggests it).

**Doubles.**

- `TestHttpRequest` — the real `HttpRequest` with its input given by the test: `new TestHttpRequest($path, $get, $post, $method, $cookie, $server, $body, $files)` (the path without its query; the web server's variables over the defaults: the script `/index.php`, the host `example.test`, the client `192.0.2.1`; the files in `$_FILES`' shape, their temporary paths files the test wrote, handed out as `TestUploadedFile`, which `moveTo()` moves as PHP moves an upload). `flush()` sends nothing, and the cookies are recorded instead of sent: `getCookiesSet()`, `getCookieAttributes($name)`, `getResponseStatusCode()`, `getHeaders()`, `getHeader($name)` (the last of the name, in any case), `getRedirect()`, `assertRedirect($target, $code = 303)`, `requireResponse()` (the page: a redirect fails it), `setRemoteAddress()`, `setServerParam()`.
- `TestCliRequest` — the real `CliRequest` of the arguments the test gives (`['user/create', 'ada']`, as they follow `php bin/index.php`); `flush()` prints nothing, `getResponseBody()` is what it would print.
- `MemorySessionService` — a browser's session without PHP's: a serialized copy of every value (what cannot be serialized fails as on a server); after `close()` or `destroy()` what the request writes is not kept until `open()` (the next request); `regenerateId()` and `renew()` give a new `id()` and are refused once the session is closed; an attribute needs a name.
- `CheapHasherService` — the framework's hasher at bcrypt's lowest cost and without its random wait. The stand-in hash that a check with nothing to compare (a blank string, a stored value that is no bcrypt hash) and `avoidTimingAttack()` verify against is of that cost too (its protected constant `TOKEN_TIMING_ATT`, a hash of a text nobody types), so that such a check is as cheap in a test as any other.
- `ExpectsExactMessage` — a trait for a test case: `expectExceptionMessageExactly($message)` pins the whole message, which PHPUnit's `expectExceptionMessage()` only finds somewhere in it.

**`ApplicationTestCase`** (abstract) runs the application in the test's process, a subclass naming its `projectRoot()`. Every simulated request runs in a scope of its own — a new bean factory, as a request in production runs in a process of its own —, over `configurationFiles($transport)`: the framework's `config/default.php` and `config/<transport>.php`, the application's two, and its `tests/Support/config/integration.php` in place of `config/local.php` (a missing file is named), booted once in a test. In every scope a `CheapHasherService`, `$this->session` (a `MemorySessionService`: the browser's, kept from request to request; `useSession()` continues as another browser), and the test's doubles for the beans it named (`useBean($id, $double)`, from then on). `get($path, $get, $cookie, $server)` and `post($path, $post, $get, $withToken = true, $cookie, $server, $files)` — the one-time token in the query, issued into the session as a rendered form's is — go through `dispatch($request)`, which opens the session, makes `$this->remoteAddress` the client and returns the request with its answer; `cli($arguments)` returns what the command would print and `cliExitCode()` its exit code; `bean($id, $transport = 'http')` is a bean of a scope of its own; `configure($domain, $key, $value)` sets a value of `configuration.service`. A scope is released when the next request starts, or when the test ends. PHP's error log is a file of the test's own (`errorLog()`), and **a test fails when the application wrote to it** unless it said so (`expectLoggedFailure()`). The hooks `scopeCreated()`, `scopeReleased()` and `beforeDispatch()` do nothing here: a subclass adds there what every scope needs (a database, an account). A subclass that overrides `setUp()` or `tearDown()` calls the parent's.

**Guards** (`ampf\Testing\Guard`) are abstract test cases an application extends in a small class that names its `projectRoot()`; each fails with a message that lists every problem:

- `ClassLoadingGuard` — every PHP file under `sourceDirectory()` (`src`) declares the type its path names under `sourceNamespace()`, in the path's exact case.
- `BeanConfigurationGuard` — every bean of `config/default.php` is keyed by a type its class is, a singleton by an interface; every route of each transport (`transports()`, the web and the command line) names a bean that is a controller; no route comes after a catch-all (a route that takes every route).
- `RouteConstantsGuard` — the constants `ROUTE_ID_*` and `CLI_ROUTE_ID_*` of `routeConstantsClass()` are exactly the route ids of `config/http.php` and `config/cli.php`, no two of one value, and `catchAllRouteId()` and `cliCatchAllRouteId()` are the last routes.
- `BeanAccessGuard` — the access traits are what the generator writes as `config/cli.php` configures it (its `check`, section 4).

```php
class NotesTest extends ApplicationTestCase
{
    protected static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testANoteIsKept(): void
    {
        $this->post('notes', ['text' => 'Buy milk'])->assertRedirect('/notes');
        self::assertStringContainsString('Buy milk', $this->get('notes')->requireResponse());
    }
}
```

## 14. Working on ampf

[`AGENTS.md`](AGENTS.md) has the conventions and the working rules; the tests live under `tests/` (`ampf\Tests\`): a unit suite, and an integration suite that runs a small application on the framework (`tests/Fixtures/App`) in process, as commands and behind PHP's built-in web server. The development container needs nothing but Docker — PHP 8.5 with the framework's extensions, PCOV and Composer, as throwaway containers that run as the calling user, without a network unless one is needed:

```sh
sh docker/ci                 # everything: dependencies, lint, PHPCS, PHP-CS-Fixer, PHPStan, the tests with the coverage,
                             # the mutation testing
sh docker/ci static          # all but the tests and the mutation testing
sh docker/phpcs              # PHP_CodeSniffer (phpcs.xml.dist, the standard applications extend); docker/phpcbf fixes
sh docker/php-cs-fixer       # PHP-CS-Fixer, dry run (sh docker/php-cs-fixer fix applies it)
sh docker/phpstan            # PHPStan at the maximum level
sh docker/phpunit            # PHPUnit; arguments go through (--testsuite unit, --filter …)
sh docker/infection          # Infection, the mutation testing (src/View/HttpView.php for one file)
sh docker/composer update    # Composer, with the network
sh docker/run php -v         # anything else in the container
```

Each check stands at zero findings, and the mutation testing at 100 %: Infection changes the code under `src/` in some two thousand small ways — every mutator of its default profile and of the ones it leaves out, on covered and uncovered code alike — and the unit suite fails for each of them (`infection.json5.dist` says what is left out, and why; the reports land in `cache/infection/`). On a machine with PHP 8.5, the Composer scripts run the same tools: `composer phpcs`, `composer cs:check`, `composer phpstan`, `composer test`.

## License

MIT, see [LICENSE](LICENSE).
