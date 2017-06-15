<?php

namespace ampf\views;

use \ampf\beans\BeanFactoryAccess;

abstract class AbstractView implements BeanFactoryAccess, View
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
	use \ampf\beans\access\TranslatorServiceAccess;
	use \ampf\beans\access\ViewResolverAccess;

	/**
	 * @var array
	 */
	protected $memory = array();

	/**
	 * @var \DateTimeZone
	 */
	protected $timezone_utc = null;

	/**
	 * @var \DateTimeZone
	 */
	protected $timezone_local = null;

	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get(string $key, $default = null)
	{
		if (!$this->has($key))
		{
			return $default;
		}
		return $this->memory[$key];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set(string $key, $value)
	{
		$this->memory[$key] = $value;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key)
	{
		return isset($this->memory[$key]);
	}

	/**
	 * @param string $view
	 * @return string
	 */
	public function render(string $view)
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

	/**
	 * @param string $viewID
	 * @param array $params
	 * @return string
	 */
	public function subRender(string $viewID, array $params = null)
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

	/**
	 * @param numeric $number
	 * @param int $decimals
	 * @param string $decPoint
	 * @param string $thousandsSep
	 * @return string
	 */
	public function formatNumber($number, int $decimals = null, string $decPoint = null, string $thousandsSep = null)
	{
		if (is_null($decimals)) $decimals = 0;
		if (is_null($decPoint)) $decPoint = '.';
		if (is_null($thousandsSep)) $thousandsSep = ' ';

		return number_format($number, $decimals, $decPoint, $thousandsSep);
	}

	/**
	 * @param \DateTime|numeric|null $time Either a \DateTime instance or a numeric representing an UNIX timestamp
	 * @param string $format A \DateTime::format compatible string
	 * @return string
	 * @throws \Exception
	 */
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

	/**
	 * @param string $key
	 * @param array $args
	 * @return string
	 */
	public function t(string $key, array $args = null)
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
