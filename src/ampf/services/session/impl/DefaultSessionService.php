<?php

declare(strict_types=1);

namespace ampf\services\session\impl;

use ampf\services\session\SessionService;
use RuntimeException;

/**
 * phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class DefaultSessionService implements SessionService
{
    public function __construct()
    {
        if (session_start() === false) {
            throw new RuntimeException('Failed to start session.');
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                (time() - 42000),
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }
        session_destroy();
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

        $_SESSION[$key] = $value;
    }
}
