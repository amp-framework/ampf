<?php

namespace ampf\doctrine\impl;

use \Doctrine\ORM\Tools\Setup;
use \Doctrine\ORM\EntityManager;
use \ampf\beans\BeanFactoryAccess;
use \ampf\doctrine\EntityManagerFactory;

class DefaultEntityManagerFactory implements BeanFactoryAccess, EntityManagerFactory
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
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

		// Change mySQL enum internally to string
		$platform = $this->_em->getConnection()->getDatabasePlatform();
		$platform->registerDoctrineTypeMapping('enum', 'string');
	}

	/**
	 * @return EntityManager
	 */
	public function get()
	{
		return $this->_em;
	}
}
