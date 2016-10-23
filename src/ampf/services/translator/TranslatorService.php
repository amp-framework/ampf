<?php

namespace ampf\services\translator;

interface TranslatorService
{
	/**
	 * @param string $translation
	 * @param bool $ignoreCase
	 * @return string
	 */
	public function getKey(string $translation, bool $ignoreCase = true);

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getLanguage();

	/**
	 * @param string $language
	 * @throws \Exception
	 */
	public function setLanguage(string $language);

	/**
	 * @param string $key
	 * @param array $args
	 * @return string
	 * @throws \Exception
	 */
	public function translate(string $key, array $args = null);
}
