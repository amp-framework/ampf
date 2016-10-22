<?php

namespace ampf\doctrine\entities;

use \Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="\ampf\doctrine\repositories\Test")
 * @ORM\Table(
 *   name="tests",
 *   options={
 *     "collate"="utf8mb4_unicode_ci",
 *     "charset"="utf8mb4"
 *   }
 * )
 */
class Test extends Base
{
	/**
	 * @var int
	 * @ORM\Id
	 * @ORM\Column(type="bigint", options={"unsigned"=true})
	 * @ORM\GeneratedValue
	 */
	protected $id;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected $entry;

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getEntry()
	{
		return $this->entry;
	}

	/**
	 * @param string $entry
	 */
	public function setEntry(string $entry)
	{
		$this->entry = $entry;
	}
}
