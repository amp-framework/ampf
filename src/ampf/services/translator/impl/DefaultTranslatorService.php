<?php

namespace ampf\services\translator\impl;

use ampf\services\translator\TranslatorService;

class DefaultTranslatorService implements TranslatorService
{
	use \ampf\beans\access\BeanFactoryAccess;

	protected $_config = null;

	public function translate($key)
	{
		if (trim($key) == '') throw new \Exception();
		if (!isset($this->getConfig()[$key])) return null;
		return $this->getConfig()[$key];
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

		if (!isset($config['translation.file'])) throw new \Exception();
		$transFile = $config['translation.file'];
		if (trim($transFile) == '') throw new \Exception();
		if (!file_exists($transFile)) throw new \Exception();

		ob_start();
		$this->_config = require($transFile);
		ob_end_clean();
	}
}
