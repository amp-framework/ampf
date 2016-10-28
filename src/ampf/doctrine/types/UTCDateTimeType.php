<?php

namespace ampf\doctrine\types;

use \Doctrine\DBAL\Platforms\AbstractPlatform;
use \Doctrine\DBAL\Types\ConversionException;
use \Doctrine\DBAL\Types\DateTimeType;

class UTCDateTimeType extends DateTimeType
{
	/**
	 * @var \DateTimeZone
	 */
	static protected $utc;

	/**
	 * @param \DateTime $value
	 * @param AbstractPlatform $platform
	 * @return mixed
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform)
	{
		if ($value instanceof \DateTime)
		{
			$value->setTimezone(static::getUtc());
		}

		return parent::convertToDatabaseValue($value, $platform);
	}

	/**
	 * @param \DateTime $value
	 * @param AbstractPlatform $platform
	 * @return \DateTime
	 * @throws \Doctrine\DBAL\Types\ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform)
	{
		if ($value === null || ($value instanceof \DateTime))
		{
			return $value;
		}

		// Create the DateTime object with UTC timezone
		$converted = \DateTime::createFromFormat(
				$platform->getDateTimeFormatString(), $value, static::getUtc()
		);
		if (!$converted)
		{
			throw ConversionException::conversionFailedFormat(
				$value, $this->getName(), $platform->getDateTimeFormatString()
			);
		}


		return $converted;
	}

	/**
	 * @return \DateTimeZone
	 */
	static protected function getUtc()
	{
		if (static::$utc === null)
		{
			static::$utc = new \DateTimeZone('UTC');
		}
		return static::$utc;
	}

}
