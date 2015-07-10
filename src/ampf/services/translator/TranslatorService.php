<?php

namespace ampf\services\translator;

interface TranslatorService
{
	/**
	 * @param string $translation
	 * @param bool $ignoreCase
	 * @return string
	 */
	public function getKey($translation, $ignoreCase = true);

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
