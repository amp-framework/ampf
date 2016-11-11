<?php

namespace ampf\views;

use \ampf\beans\BeanFactoryAccess;

abstract class AbstractView implements BeanFactoryAccess, View
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
	use \ampf\beans\access\TranslatorServiceAccess;
	use \ampf\beans\access\ViewResolverAccess;

	protected $memory = array();

	/**
	 * @var \DateTimeZone
	 */
	protected $timezone_utc = null;

	/**
	 * @var \DateTimeZone
	 */
	protected $timezone_local = null;

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

	public function formatTime($time = null, string $format = null)
	{
		// If not instanceof DateTime, try to create from unix timestamp
		if (!($time instanceof \DateTime))
		{
			$time = \DateTime::createFromFormat('U', $time, $this->getTimeZoneUTC());
			if (!$time) throw new \Exception();
		}

		if (is_null($format))
		{
			$format = 'd.m.Y H:i';
		}

		// Convert to local timezone
		$datetime = clone($time);
		/* @var $datetime \DateTime */
		$datetime->setTimezone($this->getTimeZoneLocal());

		return $datetime->format($format);
	}

	public function t($key, $args = null)
	{
		return $this->getTranslatorService()->translate($key, $args);
	}

	/**
	 * @return \DateTimeZone
	 */
	protected function getTimeZoneLocal()
	{
		if ($this->timezone_local === null)
		{
			$this->timezone_local = new \DateTimeZone(date_default_timezone_get());
		}
		return $this->timezone_local;
	}

	/**
	 * @return \DateTimeZone
	 */
	protected function getTimeZoneUTC()
	{
		if ($this->timezone_utc === null)
		{
			$this->timezone_utc = new \DateTimeZone('UTC');
		}
		return $this->timezone_utc;
	}
}
