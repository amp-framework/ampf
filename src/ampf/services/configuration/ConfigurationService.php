<?php

namespace ampf\services\configuration;

interface ConfigurationService
{
	/**
	 * Gets a configuration value, optinally by given an override domain.
	 *
	 * @param string $key
	 * @param string|null $domain
	 * @return string|null
	 */
	public function get(string $key, string $domain = null);

	/**
	 * Sets a domain for the used configuration. This should be a string in the form
	 * ".a.b.c", where a, b and c are treated as first-, second- and third-level domains.
	 * This enables the use of fallback configuration values; if a configuration value e.g.
	 * exists in the domain ".a.b" and ".a.b.c", the value of ".a.b.c" is used when ".a.b.c" is set
	 * as the used domain - if e.g. ".a.b.d" is set as the domain, the value of ".a.b" is being used.
	 *
	 * @param string $domain
	 * @return ConfigurationService
	 */
	public function setDomain(string $domain);
}
