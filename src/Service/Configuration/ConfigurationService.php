<?php

declare(strict_types=1);

namespace ampf\Service\Configuration;

use RuntimeException;

/** The domains of the merged configuration's `configuration.service` block (domain => key => value). */
class ConfigurationService implements ConfigurationServiceInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $config = [];

    protected ?string $domain = null;

    /**
     * @throws RuntimeException when neither the call nor setDomain() names a domain
     */
    public function get(string $key, ?string $domain = null): mixed
    {
        $domain ??= $this->domain ?? throw new RuntimeException(
            'The configuration service has no domain: name one, or set one with setDomain().',
        );

        // ".app.de.admin", then ".app.de", then ".app": the narrowest domain that has the key wins
        for ($parts = explode('.', $domain); count($parts) > 1; array_pop($parts)) {
            $value = $this->config[implode('.', $parts)][$key] ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws RuntimeException when the block is no array of domains
     */
    public function setConfig(array $config): void
    {
        $domains = $config['configuration.service'] ?? null;

        if (!is_array($domains)) {
            throw new RuntimeException(
                'The configuration\'s configuration.service must be an array of domains, not ' . get_debug_type(
                    $domains,
                ) . '.',
            );
        }

        foreach ($domains as $domain => $values) {
            if (!is_string($domain) || !is_array($values)) {
                throw new RuntimeException(
                    'The configuration\'s configuration.service must map each domain to an array of its values.',
                );
            }
        }

        /** @var array<string, array<string, mixed>> $domains the keys of a domain's values are PHP's array keys */
        $this->config = $domains;
    }
}
