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
			WHERE `" . static::$_ID_KEY . "` = :ID
			LIMIT 1
		";
		$sth = $this->getPDO()->prepare($query);
		$sth->execute(array('ID' => $model->{static::$_ID_KEY}));
		if ($sth->rowCount() != 1)
		{
			throw new \Exception();
		}
		$model->{static::$_ID_KEY} = null;
	}

	/**
	 * @param int $start
	 * @param int $limit
	 * @return \ampf\database\models\AbstractModel[]
	 */
	public function getAll($start = null, $limit = null)
	{
		$query = "
			SELECT * FROM `{$this->TABLE}`
		";
		if ($limit !== null && is_int($limit))
		{
			$limit = ((int)$limit);
			if ($start === null || !is_int($start))
			{
				$start = 0;
			}
			$query .= "
				LIMIT {$start},{$limit}
			";
		}
		return $this->getModels($query);
	}

	/**
	 * @param array $ids
	 * @return \ampf\database\models\AbstractModel[]
	 * @throws \Exception
	 */
	public function getAllByID($ids)
	{
		if (!is_array($ids)) throw new \Exception();
		if (count($ids) < 1) return array();

		$where = array();
		$params = array();
		$i = 0;
		foreach ($ids as $id)
		{
			$where[] = "`" . static::$_ID_KEY . "` = :id{$i}";
			$params['id' . $i] = $id;
			$i++;
		}

		$query = "
			SELECT *
			FROM `{$this->TABLE}`
			WHERE " . implode(" OR ", $where) . "
		";

		$result = $this->getModels($query, $params);
		if (count($result) < 1) return array();
		return $result;
	}

	/**
	 * @return int
	 */
	public function getAllCount()
	{
		$query = "
			SELECT COUNT(*) as `count`
			FROM `{$this->TABLE}`
		";
		$objects = $this->getObjects($query);
		if (!is_array($objects) || count($objects) < 1)
		{
			return 0;
		}
		return reset($objects)->count;
	}

	public function getByID($ID)
	{
		if (trim($ID) == '') throw new \Exception();

		$query = "
			SELECT *
			FROM `{$this->TABLE}`
			WHERE `" . static::$_ID_KEY . "` = :ID
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
		$ID = $properties[static::$_ID_KEY];
		unset($properties[static::$_ID_KEY]);

		$set = array();
		$params = array();
		foreach ($properties as $key => $value)
		{
			$set[] = "`{$key}` = :{$key}";
			$params[$key] = $value;
		}
		$set = implode(", ", $set);

		if (is_null($ID))
		{
			$query = "
				INSERT INTO `{$this->TABLE}`
				SET {$set}
			";
			$sth = $this->getPDO()->prepare($query);
			$sth->execute($params);
			$model->{static::$_ID_KEY} = $this->getPDO()->lastInsertId();
		}
		else
		{
			$params['ID'] = $ID;
			$query = "
				UPDATE `{$this->TABLE}`
				SET {$set}
				WHERE `" . static::$_ID_KEY . "` = :ID
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
