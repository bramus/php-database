<?php

namespace Bramus\Database;

/**
 * Represents a base Repository that uses soft-deletion through an is_deleted field ('Y','N')
 */
abstract class RepositoryWithSoftDeletionUsingDeletedOn extends Repository
{
	/**
	 * Returns the default conditions that apply when performing fetches
	 * @return array [description]
	 */
	public function getDefaultConditions()
	{
		return [
			'deleted_on' => null,
		];
	}

	/**
	 * (Soft) Deletes a record
	 * @param  array  $identifier [description]
	 * @return [type]             [description]
	 */
	public function delete(array $identifier)
	{
		return $this->db->update($this->getTableName(), [
			'deleted_on' => $this->now(),
			'deleted_by' => self::getGlobal('user_id'),
			'deleted_ip' => self::getGlobal('user_ip'),
		], $identifier);
	}
}
