<?php

namespace ampf\services\configuration\impl;

use \ampf\services\configuration\ConfigurationService;

class DefaultConfigurationService implements ConfigurationService
{
	/**
	 * @var array
	 */
	protected $config = array();

	/**
	 * @var string
	 */
	protected $domain = null;

	/**
	 * @param string $key
	 * @param string|null $domain
	 * @return string|null
	 */
	public function get(string $key, string $domain = null)
	{
		if ($domain === null)
		{
			$domain = $this->domain;
		}

		while (
			strpos($domain, '.') !== false
			&& trim($domain) != ''
		)
		{
			if (
				isset($this->config[$domain])
				&& isset($this->config[$domain][$key])
			)
			{
				return $this->config[$domain][$key];
			}

			$domain = substr($domain, 0, strrpos($domain, '.'));
		}
	}

	/**
	 * @param string $domain
	 * @return ConfigurationService
	 */
	public function setDomain(string $domain)
	{
		$this->domain = $domain;
	}

	/**
	 * Bean setters
	 */

	public function setConfig(array $config)
	{
		if (
			!isset($config['configuration.service'])
			|| !is_array($config['configuration.service'])
		)
		{
			throw new \Exception();
		}

		$this->config = $config['configuration.service'];
	}
}
