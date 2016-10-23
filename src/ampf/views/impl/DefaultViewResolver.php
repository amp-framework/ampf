<?php

namespace ampf\views\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\views\ViewResolver;

class DefaultViewResolver implements BeanFactoryAccess, ViewResolver
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;

	protected $_viewDirectory = null;

	public function getViewFilename($view)
	{
		if (!$this->isValidFilename($view))
		{
			throw new \Exception();
		}
		$path = ($this->getViewDirectory() . '/' . $view);
		if (!file_exists($path))
		{
			throw new \Exception();
		}
		return $path;
	}

	protected function isValidFilename($filename)
	{
		// replace backslashes with slashes (windows)
		$filename = str_replace('\\', '/', $filename);
		// explode by slashes
		$array = explode('/', $filename);
		foreach ($array as $value)
		{
			// more than 1 dot is not allowed
			if (strpos($value, '..') !== false) return false;
			// A-Z a-z 0-9 _ . -
			if (!preg_match('/^[A-Za-z0-9_\.\-]+$/', $value)) return false;
		}
		return true;
	}

	// Bean getters

	public function getViewDirectory()
	{
		if (is_null($this->_viewDirectory))
		{
			$this->setConfig($this->getBeanFactory()->get('Config'));
		}
		return $this->_viewDirectory;
	}

	// Bean setters

	public function setConfig($config)
	{
		if (!is_array($config) || count($config) < 1) throw new \Exception();
		if (!isset($config['viewDirectory'])) throw new \Exception();

		$this->_viewDirectory = realpath($config['viewDirectory']);
	}
}
