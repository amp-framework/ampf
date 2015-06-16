<?php

namespace ampf\database\mapper;

abstract class AbstractMapper implements Mapper
{
	protected static $_ID_KEY = "ID";

	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\DatabaseFactoryAccess;

	protected $modelCacheByID = array();

	// Needs to be overriden by child-classes.
	protected $TABLE = null;
	// Needs to be overriden by child-classes.
	protected $MODEL = null;
	// Can be overriden by child-classes
	protected $NULL_FIELDS = array();

	public function __construct()
	{
		if (!is_string($this->TABLE) || trim($this->TABLE) == '') throw new \Exception();
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $this->TABLE)) throw new \Exception();
		if (is_null($this->MODEL)) throw new \Exception();
	}

	public function create()
	{
		$model = $this->getBeanFactory()->get($this->MODEL);
		return $model;
	}

	public function delete($model)
	{
		if (!$this->is($model)) throw new \Exception();

		$query = "
			DELETE FROM `{$this->TABLE}`
			WHERE `" . self::$_ID_KEY . "` = :ID
			LIMIT 1
		";
		$sth = $this->getPDO()->prepare($query);
		$sth->execute(array('ID' => $model->ID));
		if ($sth->rowCount() != 1)
		{
			throw new \Exception();
		}
		$model->{self::$_ID_KEY} = null;
	}

	public function getAll()
	{
		$query = "
			SELECT * FROM `{$this->TABLE}`
		";
		return $this->getModels($query);
	}

	public function getByID($ID)
	{
		if (trim($ID) == '') throw new \Exception();

		$query = "
			SELECT *
			FROM `{$this->TABLE}`
			WHERE `" . self::$_ID_KEY . "` = :ID
			LIMIT 1
		";
		$result = $this->getModels($query, array('ID' => $ID));
		if (count($result) != 1) return null;
		return $result[0];
	}

	public function is($model)
	{
		if (!is_object($model)) return false;
		return ($this->getBeanFactory()->is($model, $this->MODEL));
	}

	public function save($model)
	{
		if (!$this->is($model))
		{
			throw new \Exception();
		}

		$properties = $model->getAllProperties();
		$ID = $properties[self::$_ID_KEY];
		unset($properties[self::$_ID_KEY]);

		$set = array();
		$params = array();
		foreach ($properties as $key => $value)
		{
			if ($value === null && isset($this->NULL_FIELDS[$key]))
			{
				$set[] = "`{$key}` = NULL";
			}
			else
			{
				$set[] = "`{$key}` = :{$key}";
				$params[$key] = $value;
			}
		}
		$set = implode(", ", $set);

		if (is_null($ID))
		{
			$query = "
				INSERT INTO `{$this->TABLE}`
				SET {$set}
			";
			$sth = $this->getPDO()->prepare($query);
			$model->{self::$_ID_KEY} = $this->getPDO()->lastInsertId();
		}
		else
		{
			$params[self::$_ID_KEY] = $ID;
			$query = "
				UPDATE `{$this->TABLE}`
				SET {$set}
				WHERE `" . self::$_ID_KEY . "` = :ID
			";
			$sth = $this->getPDO()->prepare($query);
			$sth->execute($params);
		}
	}

	public function saveAll($models)
	{
		if (!is_array($models) || count($models) < 1) throw new \Exception();
		foreach ($models as $model)
		{
			$this->save($model);
		}
		return true;
	}

	// Protected methods

	/**
	 * @param string $query
	 * @param array $params
	 * @return array
	 * @throws \Exception
	 */
	protected function getModels($query, $params = null)
	{
		$rows = $this->getObjects($query, $params);
		$return = array();
		foreach ($rows as $row)
		{
			$model = $this->getBeanFactory()->get($this->MODEL);
			$model->fillByStdClass($row);
			$return[] = $model;
		}

		return $return;
	}

	/**
	 * @param string $query
	 * @param array $params
	 * @return array
	 * @throws \Exception
	 */
	protected function getObjects($query, $params = null)
	{
		if ($params === null) $params = array();
		if (!is_array($params)) throw new \Exception();
		if (trim($query) == '') throw new \Exception('Query is empty.');

		$sth  = $this->getPDO()->prepare($query);
		$sth->execute($params);
		$return = array();
		while (($row = $sth->fetchObject()) !== false)
		{
			$return[] = $row;
		}

		return $return;
	}

	/**
	 * @return \PDO
	 */
	protected function getPDO()
	{
		return $this->getDatabaseFactory()->getPDO();
	}
}
