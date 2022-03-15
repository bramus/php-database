<?php

namespace Bramus\Database;

/**
 * Represents a base Repository that uses soft-deletion through a deleted_on field (null or DATETIME)
 */
abstract class RepositoryWithSoftDeletionUsingIsDeleted extends Repository
{
	/**
	 * Returns the default conditions that apply when performing fetches
	 * @return array [description]
	 */
	public function getDefaultConditions()
	{
		return [
			'is_deleted' => 'N',
		];
	}

	/**
	 * (Soft) Deletes a record
	 * @param  array  $identifier [description]
	 * @return [type]             [description]
	 */
	public function delete(array $identifier)
	{
		return $this->db->update($this->getTableName(), ['is_deleted' => 'Y'], $identifier);
	}
}
