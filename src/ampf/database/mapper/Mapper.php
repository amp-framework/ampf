<?php

namespace ampf\database\mapper;

interface Mapper
{
	public function create();

	public function delete($model);

	public function getAll();

	public function getByID($ID);

	public function is($model);

	public function save($model);

	public function saveAll($models);
}
