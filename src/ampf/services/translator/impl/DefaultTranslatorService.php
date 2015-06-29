<?php

namespace ampf\services\translator\impl;

use ampf\services\translator\TranslatorService;

class DefaultTranslatorService implements TranslatorService
{
	use \ampf\beans\access\BeanFactoryAccess;

	/**
	 * @var array
	 */
	protected $_config = null;

	/**
	 * @var string
	 */
	protected $language = null;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getLanguage()
	{
		if ($this->language === null)
		{
			throw new \Exception('No language set');
		}
		return $this->language;
	}

	/**
	 * @param string $language
	 * @throws \Exception
	 */
	public function setLanguage($language)
	{
		if (trim($language) == '') throw new \Exception();
		$this->language = $language;
	}

	/**
	 * @param string $key
	 * @param array $args
	 * @return string
	 * @throws \Exception
	 */
	public function translate($key, $args = null)
	{
		if (trim($key) == '') throw new \Exception();

		$value = $this->getValue($key);
		if ($value === null) return null;

		if (is_array($args) && count($args) > 0)
		{
			$value = sprintf($value, $args);
		}

		return $value;
	}

	/**
	 * Protected methods
	 */

	protected function getValue($key)
	{
		$config = $this->getConfig();
		if (!isset($config[$key]))
		{
			return $key;
		}
		return $config[$key];
	}

	// Bean getters

	public function getConfig()
	{
		if (is_null($this->_config))
		{
			$this->setConfig($this->getBeanFactory()->get('Config'));
		}
		return $this->_config;
	}

	// Bean setters

	public function setConfig($config)
	{
		if (!is_array($config) || count($config) < 1) throw new \Exception();

		if (!isset($config['translation.dir'])) throw new \Exception();
		$transFile = ($config['translation.dir'] . '/' . $this->getLanguage() . '.php');
		if (!file_exists($transFile)) throw new \Exception();

		ob_start();
		$this->_config = require($transFile);
		ob_end_clean();
	}
}
