<?php

namespace ampf\views;

abstract class AbstractView implements View
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\TranslatorServiceAccess;
	use \ampf\beans\access\ViewResolverAccess;

	protected $memory = array();

	public function get($key)
	{
		if (!isset($this->memory[$key]))
		{
			return null;
		}
		return $this->memory[$key];
	}

	public function set($key, $value)
	{
		$this->memory[$key] = $value;
	}

	public function render($view)
	{
		$path = $this->getViewResolver()->getViewFilename($view);

		foreach ($this->memory as $key => $value)
		{
			if ($key != 'path' && $key != 'this')
			{
				$$key = $value;
			}
		}

		ob_start();
		require($path);
		$result = ob_get_clean();

		return $result;
	}

	public function reset()
	{
		$this->memory = array();
	}

	public function subRender($viewID, $params = null)
	{
		if (is_null($params)) $params = array();
		// get a new view
		$view = $this->getBeanFactory()->get('View');
		// set the params
		foreach ($params as $key => $value)
		{
			$view->set($key, $value);
		}
		// render the output and return it
		return $view->render($viewID);
	}

	public function formatNumber($number, $decimals = null, $decPoint = null, $thousandsSep = null)
	{
		if (is_null($decimals)) $decimals = 0;
		if (is_null($decPoint)) $decPoint = '.';
		if (is_null($thousandsSep)) $thousandsSep = ' ';

		return number_format($number, $decimals, $decPoint, $thousandsSep);
	}

	public function formatTime($time = null, $format = null)
	{
		if (is_null($time)) $time = time();
		if (!is_numeric($time))
		{
			$time = @strtotime($time);
			// fallback (1st january 1970)
			if ($time == false) $time = 0;
		}
		if (is_null($format))
		{
			$format = '%d.%m.%Y %H:%M';
		}

		return strftime($format, $time);
	}

	public function t($key, $args = null)
	{
		return $this->getTranslatorService()->translate($key, $args);
	}
}
