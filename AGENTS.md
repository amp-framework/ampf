# ampf: agent guide

ampf (`amp-framework/ampf`, MIT) is a PHP MVC framework: a lazy, explicitly configured bean factory, routes to controller beans, a request that carries the response, PHP templates. [`README.md`](README.md) describes the framework; this file is the orientation for working **on** it — the rules, the conventions and how a change is validated. It is kept current: a task that changes a convention changes it here in the same change.

ampf has consumers: applications install it (often as `dev-master`), subclass its classes and extend its coding standard, so a change here can break them.

## Documents

| Read | For |
| --- | --- |
| `README.md` | The framework: boot, configuration merge, beans, access traits, routes, request, views, CLI, Doctrine, services, configuration keys, the source tree. |
| `UPGRADING.md` | What an application changes when it moves to a newer ampf: renamed classes and bean ids, changed behaviour. Every change that breaks a caller adds its entry here. |

## Rules for every task

1. **Read this file, then `git status --short`.** Preserve unrelated changes. Never commit, push or tag — **the maintainer commits**.
2. **Every behaviour change gets its own test, proven red on the previous source** (`git stash push -- src config`; run the test; `git stash pop`). A move or a format change contains no behaviour change; a change that mixes both is split.
3. **Validation before handing over:** PHPCS 0, PHP-CS-Fixer 0 and PHPStan 0 over `src/`, `config/` and `tests/`, and the whole test suite green (the commands below). No baseline, no blanket ignore: an ignore names its error and says why.
4. **Applications come first.** Before a public name changes or goes — a class, an interface or one of its methods, a trait, a bean id, a configuration key, a rule of `phpcs.xml.dist` (applications extend it as their coding standard) — write what an application must change into `UPGRADING.md`.
5. **Observed before suspected.** Run the code — the suites, a short script — rather than reason about it, and say what was observed and what is only suspected.
6. **Bidirectional audit before removing anything:** its inbound references *and* what it does — the calls it makes, the state it keeps, what else depends on it happening.
7. **No secrets:** nothing from a machine's local configuration, a database or a log goes into a test, a document or a commit.
8. **Open questions:** say explicitly what you are waiting on.

## Environment

- PHP 8.5 with the extensions `composer.json` requires, and `pdo_sqlite` for the Doctrine tests. The checks are Composer scripts (below); only `composer install` and `composer update` need the network.

## Conventions

- **Structure.** `ampf\` is `src/` (PSR-4, exact case — `ClassLoadingTest` loads every file under its path's name). Namespaces are singular and PascalCase: `Bootstrap`, `Bean`, `BeanAccess`, `Controller`, `Request`, `Router`, `View`, `Service\<Name>`, `Doctrine\{Entity,Repository,Type}`, `Helper`; no class at the root.
- **Names.** An interface carries the `Interface` suffix, and its default implementation sits next to it without a prefix (`Service\Session\SessionService implements SessionServiceInterface`); a qualifier only where it says what the implementation is (`FileStringCacheService`). An exception sits next to what throws it (`Controller\ControllerInterruptedException`).
- **Beans.** A service is keyed by its interface (`config/default.php`); the roles the transports fill differently — `Router`, `Request`, `RequestStub`, `View` — and the built-ins `BeanFactory` and `Config` are keyed by name. `BeanConfigurationTest` pins both.
- **Access traits.** One per interface-keyed service, in `BeanAccess\` one namespace level above the interface's own sub-namespace (`Service\Session\SessionServiceInterface` → `BeanAccess\Service\SessionServiceAccess`; a `Doctrine` type keeps its level), in one fixed shape, so a generator could write them: `use AbstractAccess;`, a `protected ?Type $__name = null`, a getter that fetches `Type::class` once and asserts it, a setter taking `$object`. `AccessTraitsTest` holds every trait to its bean id.
- **Extensible by design.** Applications subclass the request, the view and the services, so framework classes are not final and their members are protected rather than private. What a test must replace goes through one protected seam (`HttpRequest::sendCookie()`, `HasherService::verify()`, `FileStringCacheService::shouldSweep()`).
- **Types come from behaviour, not annotations.** Native types wherever PHP can express them; annotations only for what it cannot (generics, array shapes, `list<>`). `declare(strict_types=1)` everywhere, `===`, no loose comparisons.
- **Docblocks.** Every class, interface and trait says in a sentence or two what it is for; a method's docblock says what a caller can rely on and what it throws — not what the code already says.
- **Configuration files.** `config/default.php` holds the services (one line per bean where it fits) and a comment for every block explaining its keys; `config/http.php` and `config/cli.php` hold a transport's roles. A configuration file runs inside `ApplicationContext::boot()`'s scope: it never assigns a variable named `$config`.
- **Tests.** `tests/Unit/<the path of the class>Test.php` in `ampf\Tests\Unit\…`; doubles and fixtures in `tests/Support/` (`ampf\Tests\Support\`): `RecordingHttpRequest` (the real request fed by the test, cookies recorded), `ArraySessionService`, `ArrayTranslatorService`, `CountingHasherService`, the file caches that always or never sweep, `AccessTraitHost`, and the Doctrine sample entity and repository. Every test class names what it covers (`#[CoversClass]`, `#[CoversTrait]`); test names are sentences (`testATokenIsAcceptedOnce`). Doctrine runs against SQLite in memory; PHP's real session runs in a separate process per test (`#[RunTestsInSeparateProcesses]`). A test never writes outside the system's temporary directory and removes what it wrote.
- **Dependencies.** The latest stable release of everything; `composer.lock` is committed.

## Tool workflow

```sh
composer phpcs      # PHP_CodeSniffer over src, config and tests; phpcbf: composer phpcbf
composer cs:check   # PHP-CS-Fixer, dry run; composer cs:fix applies it
composer phpstan    # PHPStan at level max with checkExplicitMixed, the Doctrine and PHPUnit extensions
composer test       # PHPUnit: fails on any warning, notice or deprecation, wherever it comes from
```

`phpcs.xml.dist` is also the coding standard applications extend (`vendor/amp-framework/ampf/phpcs.xml.dist`): a rule changed there changes their checks. **Both formatters agree on every file** — after `phpcbf`, `cs:check` must report nothing; review phpcbf's diff before keeping it. The fixer imports the global classes an annotation names, so annotations use short names (the PHPCS rule demanding fully qualified names in annotations is off for that reason). The tools keep their caches under the ignored `cache/`.

## Repository map

| Location | Contents |
| --- | --- |
| `src/` | The framework (`ampf\`), section 12 of `README.md`. |
| `config/` | `default.php` (the services, the Doctrine defaults, cookies, session, string cache, configuration domains), `http.php` and `cli.php` (the transports' roles), `translations/` (empty; applications name their own). |
| `tests/Unit/` | The unit suite, mirroring `src/`, plus the guards `ClassLoadingTest` and `BeanConfigurationTest`. |
| `tests/Support/` | The doubles and fixtures (above). |
| `phpcs.xml.dist`, `.php-cs-fixer.dist.php`, `phpstan.neon.dist`, `phpunit.xml.dist` | The tools' configuration. |
| `cache/`, `vendor/` | Ignored; each keeps a `CACHEDIR.TAG`, so backups skip them. |
