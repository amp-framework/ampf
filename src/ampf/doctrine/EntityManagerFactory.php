<?php

namespace ampf\doctrine;

use \Doctrine\ORM\EntityManager;

interface EntityManagerFactory
{
	/**
	 * @return EntityManager
	 */
	public function get();
}
