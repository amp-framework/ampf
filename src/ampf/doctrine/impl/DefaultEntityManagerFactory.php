<?php

namespace ampf\doctrine\impl;

use \Doctrine\ORM\Tools\Setup;
use \Doctrine\ORM\EntityManager;
use \ampf\doctrine\EntityManagerFactory;

class DefaultEntityManagerFactory implements EntityManagerFactory
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\DoctrineConfigAccess;

	/**
	 * @var EntityManager
	 */
	protected $_em = null;

	public function init()
	{
		$doctrine = $this->getDoctrineConfig();

		$cache = null;
		if ($doctrine->isDevMode())
		{
			$cache = new \Doctrine\Common\Cache\ArrayCache();
		}
		else
		{
			$cache = new \Doctrine\Common\Cache\FilesystemCache($doctrine->getCacheDir());
		}

		$config = Setup::createAnnotationMetadataConfiguration(
			$doctrine->getEntities(),
			$doctrine->isDevMode(),
			$doctrine->getProxyDir(),
			$cache,
			$doctrine->useSimpleAnnotationReader()
		);
		$this->_em = EntityManager::create(
			$doctrine->getConnectionParams(),
			$config
		);
	}

	/**
	 * @return EntityManager
	 */
	public function get()
	{
		return $this->_em;
	}
}
