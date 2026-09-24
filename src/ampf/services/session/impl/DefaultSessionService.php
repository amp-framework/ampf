<?php

declare(strict_types=1);

namespace ampf\services\session\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\services\session\SessionService;
use InvalidArgumentException;
use RuntimeException;

/**
 * PHP's session, started at its first use with the cookie attributes and the ini settings of the configuration's
 * `session` block (config/default.php) in place: an HttpOnly, SameSite=Lax cookie for the whole site, Secure when the
 * configuration says so or — `secure` null — when the request came over https; strict mode (an id the server never
 * issued is replaced, not adopted) and cookies only.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class DefaultSessionService implements BeanFactoryAccess, SessionService
{
    use DefaultBeanFactoryAccess;

    protected bool $started = false;

    protected bool $closed = false;

    public function close(): void
    {
        if ($this->started && !$this->closed && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->closed = true;
    }

    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            $sessionName = session_name();

            if ($sessionName === false) {
                throw new RuntimeException();
            }

            // The deletion carries the attributes the cookie was set with, or a browser may keep the cookie
            setcookie(
                $sessionName,
                '',
                [
                    'expires' => (time() - 42_000),
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ],
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function getAttribute(string $key): mixed
    {
        if (!$this->hasAttribute($key)) {
            return null;
        }

        return $_SESSION[$key];
    }

    public function hasAttribute(string $key): bool
    {
        $this->start();

        return isset($_SESSION[$key]);
    }

    public function removeAttribute(string $key): void
    {
        if (!$this->hasAttribute($key)) {
            return;
        }

        // dereference possible objects
        $_SESSION[$key] = null;
        // and unset it completely
        unset($_SESSION[$key]);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw new RuntimeException();
        }

        $this->start();

        $_SESSION[$key] = $value;
    }

    /**
     * Starts the session once, with the cookie's attributes and the ini settings in place first. A session closed
     * before it was ever started is read and closed at once.
     */
    protected function start(): void
    {
        if ($this->started) {
            return;
        }

        // Started elsewhere (session.auto_start): its cookie went out already, nothing is left to configure
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $config = $this->getSessionConfig();
        $cookie = $this->getCookieParameters();
        $strictMode = ($config['use_strict_mode'] ?? true) === false
            ? '0'
            : '1';
        $onlyCookies = ($config['use_only_cookies'] ?? true) === false
            ? '0'
            : '1';

        if (
            ini_set('session.use_strict_mode', $strictMode) === false
            || ini_set('session.use_only_cookies', $onlyCookies) === false
            || !session_set_cookie_params($cookie)
        ) {
            throw new RuntimeException('Failed to configure the session.');
        }

        $options = $this->closed
            ? ['read_and_close' => true]
            : [];

        if (session_start($options) === false) {
            throw new RuntimeException('Failed to start session.');
        }

        $this->started = true;
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
            throw new InvalidArgumentException('session.cookie must be an array.');
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
                throw new InvalidArgumentException('Unknown or malformed session cookie attribute ' . $name . '.');
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
     */
    protected function getSessionConfig(): array
    {
        if ($this->__beanFactory === null) {
            return [];
        }

        $config = $this->getBeanFactory()->get('Config');
        $session = is_array($config)
            ? ($config['session'] ?? [])
            : [];

        return is_array($session)
            ? $session
            : [];
    }

    /** Whether the web server says the request came over TLS (its HTTPS variable, "off" meaning not). */
    protected function isHttpsRequest(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
