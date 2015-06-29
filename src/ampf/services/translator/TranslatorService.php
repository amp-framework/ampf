<?php

namespace ampf\services\translator;

interface TranslatorService
{
	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getLanguage();

	/**
	 * @param string $language
	 * @throws \Exception
	 */
	public function setLanguage($language);

	/**
	 * @param string $key
	 * @param array $args
	 * @return string
	 * @throws \Exception
	 */
	public function translate($key, $args = null);
}
