<?php

namespace ampf\doctrine\impl;

use \Doctrine\DBAL\Types\Type;
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

		foreach ($doctrine->getTypeOverrides() as $type => $override)
		{
			Type::overrideType($type, $override);
		}

		$this->_em = EntityManager::create(
			$doctrine->getConnectionParams(),
			$doctrine->getConfiguration()
		);

		if (count($doctrine->getMappingOverrides()) > 0)
		{
			$platform = $this->_em->getConnection()->getDatabasePlatform();
			foreach ($doctrine->getMappingOverrides() as $mapping => $override)
			{
				$platform->registerDoctrineTypeMapping($mapping, $override);
			}
		}
	}

	/**
	 * @return EntityManager
	 */
	public function get()
	{
		return $this->_em;
	}
}
