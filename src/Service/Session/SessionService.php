<?php

declare(strict_types=1);

namespace ampf\Service\Session;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Request\HttpRequestInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * PHP's session, started at its first use with the cookie attributes and the ini settings of the configuration's
 * `session` block (config/default.php) in place: an HttpOnly, SameSite=Lax cookie for the whole site, Secure when the
 * configuration says so or — `secure` null — when the request came over https; strict mode (an id the server never
 * issued is replaced, not adopted) and cookies only.
 *
 * With `session.lazy` on (off without it) a request that brings no session cookie has nothing to read, so reading
 * starts no session — otherwise a visitor's page that only leads on to the login (or a crawler's, or a flood's) leaves
 * a session file behind for every request. Everything but a read starts one as always (a write, a removal, a new id, a
 * destroy), and a session started in this request, by a write or elsewhere, is read as always. The cookie is the one PHP
 * names (`session.name`) in the request the bean `Request` holds, and its value must be one a session id can be: the
 * characters of PHP's ids (letters, digits, `,` and `-`), 22 to 256 of them. A cookie of any other value — forged,
 * mistyped, an array — is no cookie of a session, and a read it brings starts none; one that looks like an id but is
 * none the server issued starts a session as before (strict mode gives it a new id). A command line has no cookie.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class SessionService implements BeanFactoryAccessInterface, SessionServiceInterface
{
    use BeanFactoryAccess;

    protected bool $started = false;

    protected bool $closed = false;

    public function close(): void
    {
        // The service's session, or one started elsewhere (session.auto_start): its lock goes either way, and what it
        // read stays in $_SESSION for the rest of the request — it is not started again
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            $this->started = true;
        }

        $this->closed = true;
    }

    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            // The deletion carries the attributes the cookie was set with, or a browser may keep the cookie; it
            // expires at the first second of 1970, as PHP's own deletions do
            $params = session_get_cookie_params();
            $this->sendCookie((string)session_name(), '', [
                'expires' => 1,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function getAttribute(string $key): mixed
    {
        if ($this->hasNothingToRead()) {
            return null;
        }
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function hasAttribute(string $key): bool
    {
        if ($this->hasNothingToRead()) {
            return false;
        }
        $this->start();

        return isset($_SESSION[$key]);
    }

    public function regenerateId(): void
    {
        $this->start();

        if (session_status() !== PHP_SESSION_ACTIVE || !session_regenerate_id(true)) {
            throw new RuntimeException('Failed to give the session a new id.');
        }
    }

    public function renew(): void
    {
        $this->regenerateId();

        $_SESSION = [];
    }

    public function removeAttribute(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw new RuntimeException('A session attribute needs a name.');
        }

        $this->start();

        $_SESSION[$key] = $value;
    }

    /**
     * Whether there is no session to read: the configuration's `session.lazy` is on, none was started in this request
     * (by a write, which is then what is read, or elsewhere), and the request has no cookie of one that can be a
     * session id.
     */
    protected function hasNothingToRead(): bool
    {
        if (!$this->isLazy() || $this->started || session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }

        $request = $this->getBeanFactory()->get('Request');
        $id = $request instanceof HttpRequestInterface
            ? $request->getCookieParam((string)session_name())
            : null;

        return !is_string($id) || preg_match('/^[A-Za-z0-9,-]{22,256}$/D', $id) !== 1;
    }

    /**
     * Whether a read that brings no session cookie starts no session: the configuration's `session.lazy`, off without
     * it.
     *
     * @throws InvalidArgumentException for a value that is no boolean
     */
    protected function isLazy(): bool
    {
        $lazy = $this->getSessionConfig()['lazy'] ?? false;

        if (!is_bool($lazy)) {
            throw new InvalidArgumentException(
                'The configuration\'s session.lazy must be true or false, not ' . get_debug_type($lazy) . '.',
            );
        }

        return $lazy;
    }

    /**
     * Starts the session once, with the cookie's attributes and the ini settings in place first. A session closed
     * before it was ever started is read and closed at once.
     *
     * @throws RuntimeException when PHP does not start the session
     */
    protected function start(): void
    {
        // Started once already, or elsewhere (session.auto_start): its cookie went out, nothing is left to configure —
        // and once closed, it is not started again
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $cookie = $this->getCookieParameters();
        $strictMode = ($this->getSessionConfig()['use_strict_mode'] ?? true) === false
            ? '0'
            : '1';

        // What keeps a setting from taking — output before it — keeps the session from starting, so the start says it
        ini_set('session.use_strict_mode', $strictMode);
        // Cookies only, always: PHP deprecated the other ways of passing the id
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params($cookie);

        if (!$this->openSession($this->closed ? ['read_and_close' => true] : [])) {
            throw new RuntimeException(
                'PHP did not start the session: output before it keeps its cookie from going out.',
            );
        }

        $this->started = true;
    }

    /**
     * Starts PHP's session with the options: the one place it starts.
     *
     * @param array<string, bool> $options
     */
    protected function openSession(array $options): bool
    {
        return session_start($options);
    }

    /**
     * Hands a cookie to PHP: the one place a cookie leaves the session, so that a test can record it instead.
     *
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string} $options
     */
    protected function sendCookie(string $name, string $value, array $options): void
    {
        // @phpstan-ignore argument.type (the session cookie's own SameSite, which PHP took when the session started)
        setcookie($name, $value, $options);
    }

    /**
     * The session cookie's attributes: the configuration's `session.cookie` over the defaults — a cookie of the
     * browser session for the whole site, not readable by scripts, SameSite=Lax, and Secure — `secure` null —
     * exactly when the request came over https.
     *
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'}
     */
    protected function getCookieParameters(): array
    {
        $configured = $this->getSessionConfig()['cookie'] ?? [];

        if (!is_array($configured)) {
            throw new InvalidArgumentException(
                'The configuration\'s session.cookie must be an array, not ' . get_debug_type($configured) . '.',
            );
        }

        $lifetime = 0;
        $path = '/';
        $domain = '';
        $secure = null;
        $httpOnly = true;
        $sameSite = 'Lax';

        foreach ($configured as $name => $value) {
            if ($name === 'lifetime' && is_int($value) && $value >= 0) {
                $lifetime = $value;
            } elseif ($name === 'path' && is_string($value)) {
                $path = $value;
            } elseif ($name === 'domain' && is_string($value)) {
                $domain = $value;
            } elseif ($name === 'secure' && ($value === null || is_bool($value))) {
                $secure = $value;
            } elseif ($name === 'httponly' && is_bool($value)) {
                $httpOnly = $value;
            } elseif ($name === 'samesite' && ($value === 'Lax' || $value === 'Strict' || $value === 'None')) {
                $sameSite = $value;
            } else {
                throw new InvalidArgumentException(
                    'The configuration\'s session.cookie has an unknown or malformed attribute ' . $name . '.',
                );
            }
        }

        $secure ??= $this->isHttpsRequest();

        if ($sameSite === 'None' && !$secure) {
            throw new InvalidArgumentException('A SameSite=None cookie must be Secure.');
        }

        return [
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
    }

    /**
     * The configuration's `session` block; empty when the service runs without a bean factory.
     *
     * @return array<mixed>
     *
     * @throws InvalidArgumentException for a block that is no array
     */
    protected function getSessionConfig(): array
    {
        if ($this->__beanFactory === null) {
            return [];
        }

        $session = $this->getBeanFactory()->getConfig()['session'] ?? [];

        if (!is_array($session)) {
            throw new InvalidArgumentException(
                'The configuration\'s session must be an array, not ' . get_debug_type($session) . '.',
            );
        }

        return $session;
    }

    /** Whether the web server says the request came over TLS (its HTTPS variable, "off" meaning not). */
    protected function isHttpsRequest(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
