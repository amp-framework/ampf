<?php

declare(strict_types=1);

namespace ampf\services\configuration\impl;

use ampf\services\configuration\ConfigurationService;
use RuntimeException;

class DefaultConfigurationService implements ConfigurationService
{
    /** @var array<string, array<string, mixed>> */
    protected array $config = [];

    protected ?string $domain = null;

    public function get(string $key, ?string $domain = null): mixed
    {
        if ($domain === null) {
            $domain = $this->domain;
        }

        if ($domain === null) {
            throw new RuntimeException();
        }

        while (
            str_contains($domain, '.')
            && trim($domain) !== ''
        ) {
            if (isset($this->config[$domain], $this->config[$domain][$key])) {
                return $this->config[$domain][$key];
            }

            /** @phpstan-ignore-next-line */
            $domain = substr($domain, 0, strrpos($domain, '.'));
        }

        return null;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        if (
            !isset($config['configuration.service'])
            || !is_array($config['configuration.service'])
        ) {
            throw new RuntimeException();
        }

        $this->config = $config['configuration.service'];
    }
}
