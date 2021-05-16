<?php

declare(strict_types=1);

namespace ampf\services\configuration\impl;

use ampf\services\configuration\ConfigurationService;
use RuntimeException;

class DefaultConfigurationService implements ConfigurationService
{
    /** @var array<string, array<string, string>> */
    protected array $config = [];

    protected ?string $domain = null;

    public function get(string $key, ?string $domain = null): ?string
    {
        if ($domain === null) {
            $domain = $this->domain;
        }

        while (
            strpos($domain, '.') !== false
            && trim($domain) !== ''
        ) {
            if (
                isset($this->config[$domain], $this->config[$domain][$key])
            ) {
                return $this->config[$domain][$key];
            }

            $domain = substr($domain, 0, strrpos($domain, '.'));
        }
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
