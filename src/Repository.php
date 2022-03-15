<?php

namespace Bramus\Database;

use \Pimple;

if (!function_exists('array_depth')) {
	function array_depth(array $array)
	{
		$max_depth = 1;
		foreach ($array as $value) {
			if (is_array($value)) {
				$depth = array_depth($value) + 1;
				if ($depth > $max_depth) {
					$max_depth = $depth;
				}
			}
		}

		return $max_depth;
	}
}

// @ref https://www.php.net/manual/en/function.array-merge-recursive.php#92195
if (!function_exists('array_merge_recursive_distinct')) {
	function array_merge_recursive_distinct(array &$array1, array &$array2)
	{
		$merged = $array1;

		foreach ($array2 as $key => &$value) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
	}
}

/**
 * Represents a base Repository.
 */
abstract class Repository
{
	/**
	 * @return string
	 */
	abstract public function getTableName();

	/**
	 * @var Doctrine\DBAL\Connection
	 */
	public $db;

	const DB_IDENTIFIER = 'default';

	/**
	 * @var Pimple
	 */
	public $app;

	/**
	 * @var Array
	 */
	public static $globals = [];

	/**
	 * @param mixed $app
	 */
	public function __construct($app)
	{
		// It's a \Pimple!
		if (is_a($app, \Pimple::class)) {
			$this->app = $app;
			if (!isset($this->app['dbs']) || !isset($this->app['dbs'][static::DB_IDENTIFIER])) {
				throw new \InvalidArgumentException('The passed in $app is missing the db service/key “["db"]["' . static::DB_IDENTIFIER . '"]”. This is required for ' . __CLASS__ . ' instances to work properly');
			}
			$this->db = $this->app['dbs'][static::DB_IDENTIFIER];
		}

		// It's a \Doctrine\DBAL\Connection!
		elseif (is_a($app, \Doctrine\DBAL\Connection::class)) {
			$this->app = null;
			$this->db = $app;
		}

		// It's something else …
		else {
			throw new \InvalidArgumentException('The passed in $app must be a \Doctrine\DBAL\Connection or \Pimple instance. This is required for ' . __CLASS__ . ' instances to work properly');
		}
	}

	/**
	 * Inserts a record
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function insert(array $data)
	{
		$this->db->insert($this->getTableName(), $data);

		return $this->db->lastInsertId();
	}

	/**
	 * Updates a record
	 * @param  array  $data       [description]
	 * @param  array  $identifier [description]
	 * @return int
	 */
	public function update(array $data, array $identifier)
	{
		return $this->db->update($this->getTableName(), $data, $identifier);
	}

	/**
	 * Deletes a record
	 * @param  array  $identifier [description]
	 * @return int
	 */
	public function delete(array $identifier)
	{
		return $this->db->delete($this->getTableName(), $identifier);
	}

	/**
	 * Returns the default conditions that apply when performing fetches.
	 * @return array [description]
	 */
	public function getDefaultConditions()
	{
		return [];
	}

	/**
	 * Gets the identifying field for rows in this table. Defaults to 'id'.
	 * @return [type] [description]
	 */
	public function getIdentifyingFieldName()
	{
		return 'id';
	}

	public function getDefaultQueryStart()
	{
		return sprintf('SELECT * FROM `%s`', $this->getTableName());
	}

	public function getDefaultQueryStartForExists()
	{
		return sprintf('SELECT COUNT(*) FROM `%s`', $this->getTableName());
	}

	public function getDefaultQueryStartForNumRecords()
	{
		return sprintf('SELECT COUNT(*) FROM `%s`', $this->getTableName());
	}

	public function getDefaultQueryEndForFetchAssoc()
	{
		return ' LIMIT 1';
	}

	public function getDefaultQueryEndForFetchAll()
	{
		return '';
	}

	public function getDefaultOrderByForFetchAll()
	{
		return '';
	}

	protected function mergeConditions($first = [], $second = [])
	{
		// No extra conditions to merge?
		// ~> Quit while you're ahead!
		if (sizeof($second) == 0) {
			return $first;
		}

		// Make sure both are namespaced
		$tableName = $this->getTableName();
		if ((array_depth($first) == 1) && !isset($first[$tableName])) {
			$first = [
				$tableName => $first,
			];
		}
		if ((array_depth($second) == 1) && !isset($second[$tableName])) {
			$second = [
				$tableName => $second,
			];
		}

		// Now that we are sure both are namespaced, merge 'm recursively, giving precedence to the given conditions
		return array_merge_recursive_distinct($first, $second);
	}

	/**
	 * Injects the default conditions
	 * @param  mixed  $conditions [description]
	 * @return array
	 */
	protected function injectDefaultConditions(array $conditions = [])
	{
		$defaultConditions = $this->getDefaultConditions();
		$mergedConditions = $this->mergeConditions($defaultConditions, $conditions);

		return $mergedConditions;
	}

	/**
	 * Reworks a singular value (int or string) to a conditions array.
	 * @param  [type] $idOrConditions [description]
	 * @return [type]                 [description]
	 */
	protected function reworkSingularValueToConditionsArray($idOrConditions)
	{
		// It's a single number:
		// ~> Rework to conditions array and inject the default conditions
		if (is_numeric($idOrConditions) || is_string($idOrConditions)) {
			$tableName = $this->getTableName();
			$fieldName = $this->getIdentifyingFieldName();

			return [
				$tableName => [
					$fieldName => $idOrConditions,
				],
			];
		}

		// Nothing given? Return empty array
		if (!$idOrConditions) {
			return [];
		}

		return $idOrConditions;
	}

	/**
	 * Checks if a record exists
	 *
	 * @param  mixed  $conditions [description]
	 * @return boolean
	 */
	public function exists($conditions = [])
	{
		$queryStart = $this->getDefaultQueryStartForExists();
		$queryEnd = ' LIMIT 1';

		// Rework conditions, as this call accepts both a number or a conditions array
		$conditions = $this->reworkSingularValueToConditionsArray($conditions);

		// Inject default conditions
		$conditions = $this->injectDefaultConditions($conditions);

		$queryAndParams = $this->buildQueryAndParams($queryStart, $conditions, $queryEnd);

		return ((int) $this->db->fetchColumn($queryAndParams['query'], $queryAndParams['params']) === 1);
	}

	/**
	 * Fetches a single field from one record
	 *
	 * @param  [type] $columnName [description]
	 * @param  array  $conditions [description]
	 * @return [type]             [description]
	 */
	public function fetchColumn($columnName, array $conditions = [])
	{
		$queryStart = sprintf('SELECT `' . $columnName . '` FROM %s', $this->getTableName());
		$queryEnd = ' LIMIT 1';

		// Inject default conditions
		$conditions = $this->injectDefaultConditions($conditions);

		$queryAndParams = $this->buildQueryAndParams($queryStart, $conditions, $queryEnd);

		return $this->db->fetchColumn($queryAndParams['query'], $queryAndParams['params']);
	}

	/**
	 * Returns a record that matches id/conditions
	 *
	 * @param  mixed  $conditions [description]
	 * @return array
	 */
	public function fetchAssoc($conditions = [])
	{
		$queryStart = $this->getDefaultQueryStart();
		$queryEnd = $this->getDefaultQueryEndForFetchAssoc();

		// Rework conditions, as this call accepts both a number or a conditions array
		$conditions = $this->reworkSingularValueToConditionsArray($conditions);

		// Inject default conditions
		$conditions = $this->injectDefaultConditions($conditions);

		$queryAndParams = $this->buildQueryAndParams($queryStart, $conditions, $queryEnd);

		return $this->db->fetchAssoc($queryAndParams['query'], $queryAndParams['params']);
	}

	/**
	 * Returns all records that match conditions
	 * @param  array   $conditions   [description]
	 * @param  boolean $curPage      [description]
	 * @param  boolean $limitPerPage [description]
	 * @param  boolean $order        [description]
	 * @return array
	 */
	public function fetchAll(array $conditions = [], $curPage = false, $limitPerPage = false, $order = false)
	{
		$queryStart = $this->getDefaultQueryStart();
		$queryEnd = $this->getDefaultQueryEndForFetchAll();

		// Inject Order By Clause if need be
		if ($order) {
			$queryEnd .= sprintf(' ORDER BY %s', $order);
		} else {
			$queryEnd .= $this->getDefaultOrderByForFetchAll();
		}

		// Inject Pagination if need be
		if ($curPage && $limitPerPage) {
			$queryEnd .= sprintf(
				' LIMIT %d, %d',
				(int) (($curPage - 1) * $limitPerPage),
				(int) ($limitPerPage)
			);
		}

		// Inject default conditions
		$conditions = $this->injectDefaultConditions($conditions);

		$queryAndParams = $this->buildQueryAndParams($queryStart, $conditions, $queryEnd);

		return $this->db->fetchAll($queryAndParams['query'], $queryAndParams['params']);
	}

	/**
	 * Calculates the number of records that match conditions
	 * @param  array  $conditions [description]
	 * @return int
	 */
	public function numRecords(array $conditions = [])
	{
		$queryStart = $this->getDefaultQueryStartForNumRecords();
		$queryEnd = '';

		// Inject default conditions
		$conditions = $this->injectDefaultConditions($conditions);

		$queryAndParams = $this->buildQueryAndParams($queryStart, $conditions, $queryEnd);

		return $this->db->fetchColumn($queryAndParams['query'], $queryAndParams['params']);
	}

	/**
	 * Gets `now` in UTC
	 * @param $format The format one wants. If set to NULL the time is not formatted
	 * @return \DateTime $now
	 */
	public function now($format = 'Y-m-d H:i:s')
	{
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		if ($format === 'DateTime') {
			return $now;
		}
		if ($format) {
			return $now->format($format);
		}

		return $now;
	}

	/**
	 * Builds a query with the given conditions added to it as a WHERE clause.
	 *
	 * @param string $queryStart
	 * @param mixed $conditions
	 * @param string $queryEnd
	 */
	public function buildQueryAndParams($queryStart, $conditions = [], $queryEnd = '', $defaultParams = [])
	{
		// No conditions?
		// ~> Oh, that's easy!
		if (sizeof($conditions) === 0) {
			$query = $queryStart . $queryEnd;
			$queryParams = [];
		}

		// Conditions Found!
		// ~> Let's rework a few things … (auto joins, comparisons, etc.)
		else {
			$queryParts = [];
			$queryParams = [];

			foreach ($conditions as $fieldName => $value) {
				if (is_array($value)) {
					$tablePrefix = $fieldName;
					foreach ($value as $prefixedFieldName => $prefixedValue) {
						// We need to join
						if (mb_substr($prefixedFieldName, 0, 1) === '$') {
							if (!strstr($queryStart, ' JOIN `' . $tablePrefix . '`') && !strstr($queryStart, ' JOIN ' . $tablePrefix . '')) {
								$toAdd = ' INNER JOIN `' . $tablePrefix . '` ON `' . $tablePrefix . '`.`' . substr($prefixedFieldName, 1) . '` ' . $prefixedValue;
								$positionOfWhere = strpos($queryStart, 'WHERE');
								if (!$positionOfWhere) {
									$queryStart .= $toAdd;
								} else {
									$queryStart = substr($queryStart, 0, $positionOfWhere) . $toAdd . ' ' . substr($queryStart, $positionOfWhere);
								}
							}
						}
						// We need to inject an OR, e.g. ((foo) OR (bar) OR (qux))
						elseif (in_array(mb_substr($prefixedFieldName, 0, 1), ['|'])) {
							$localParts = [];
							foreach ($prefixedValue as $subPart) {
								// @TODO: Add that is_subclass_of check here too, to play nice with non \Bramus\Database\… instances
								$localParts[] = $subPart->queryPart;
								$queryParams = array_merge($queryParams, $subPart->queryParams);
							}
							$queryParts[] = '(' . implode(') OR (', $localParts) . ')';
						} elseif (in_array(mb_substr($prefixedFieldName, -1), ['!','<','>','≤','≥'])) {
							$operator = mb_substr($prefixedFieldName, -1);
							if ($operator == '!') {
								$operator = '!=';
							}
							if ($operator == '≤') {
								$operator = '<=';
							}
							if ($operator == '≥') {
								$operator = '>=';
							}

							// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
							if (strpos($prefixedFieldName, '(')) {
								$prefixedFieldName = mb_substr($prefixedFieldName, 0, -1);
							} else {
								$prefixedFieldName = '`' . $tablePrefix . '`.`' . mb_substr($prefixedFieldName, 0, -1) . '`';
							}

							$queryParts[] = $prefixedFieldName . ' ' . ($prefixedValue === null ? 'IS NOT' : $operator) . ' ?';
							$queryParams[] = $prefixedValue;
						} else {
							if ((gettype($prefixedValue) === 'object') && (get_class($prefixedValue) !== false)) {
								switch (true) {
									case is_subclass_of($prefixedValue, 'Bramus\Database\Conditions\Base'):
									case is_subclass_of($prefixedValue, 'Bramus\Database\Expressions\Base'):

										// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
										if (strpos($prefixedFieldName, '(')) {
											$prefixedValue->fieldName = $prefixedFieldName;
										} else {
											$prefixedValue->fieldName = '`' . $tablePrefix . '`.`' . $prefixedFieldName . '`';
										}

										$queryParts[] = $prefixedValue->queryPart;
										$queryParams = array_merge($queryParams, $prefixedValue->queryParams);

										break;

									default:
										throw new \Exception('Cannot handle expression with type ' . get_class($prefixedValue));

								}
							} else {

								// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
								if (strpos($prefixedFieldName, '(')) {
									$prefixedFieldName = $prefixedFieldName;
								} else {
									$prefixedFieldName = '`' . $tablePrefix . '`.`' . $prefixedFieldName . '`';
								}

								$queryParts[] = $prefixedFieldName . ' ' . ($prefixedValue === null ? 'IS' : '=') . ' ?';
								$queryParams[] = $prefixedValue;
							}
						}
					}
				} else {
					if (in_array(mb_substr($fieldName, -1), ['!','<','>','≤','≥'])) {
						$operator = mb_substr($fieldName, -1);
						if ($operator == '!') {
							$operator = '!=';
						}
						if ($operator == '≤') {
							$operator = '<=';
						}
						if ($operator == '≥') {
							$operator = '>=';
						}

						// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
						if (strpos($fieldName, '(')) {
							$fieldName = mb_substr($fieldName, 0, -1);
						} else {
							$fieldName = '`' . mb_substr($fieldName, 0, -1) . '`';
						}

						$queryParts[] = $fieldName . ' ' . ($value === null ? 'IS NOT' : $operator) . ' ?';
						$queryParams[] = $value;
					} else {
						if ((gettype($value) === 'object') && (get_class($value) !== false)) {
							switch (true) {
								case is_subclass_of($value, 'Bramus\Database\Conditions\Base'):
								case is_subclass_of($value, 'Bramus\Database\Expressions\Base'):

									// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
									if (strpos($fieldName, '(')) {
										$value->fieldName = $fieldName;
									} else {
										$value->fieldName = '`' . $fieldName . '`';
									}

									$queryParts[] = $value->queryPart;
									$queryParams = array_merge($queryParams, $value->queryParams);

									break;

								default:
									throw new \Exception('Cannot handle expression with type ' . get_class($value));

							}
						} else {

							// Don't escape fieldname if it contains "(" which indicates that a function is being called on a field.
							if (strpos($fieldName, '(')) {
								$fieldName = $fieldName;
							} else {
								$fieldName = '`' . $fieldName . '`';
							}

							$queryParts[] = $fieldName . ' ' . ($value === null ? 'IS' : '=') . ' ?';
							$queryParams[] = $value;
						}
					}
				}
			}

			if (sizeof($queryParts) > 0) {
				$query = $queryStart . (strstr($queryStart, 'WHERE') ? ' AND ' : ' WHERE ') . implode(' AND ', $queryParts) . $queryEnd;
			} else {
				$query = $queryStart . $queryEnd;
			}
		}

		return [
			'query' => $query,
			'params' => array_merge($defaultParams, $queryParams),
		];
	}

	/**
	 * Sets a global
	 * @param [type] $key   [description]
	 * @param [type] $value [description]
	 */
	public static function setGlobal($key, $value)
	{
		self::$globals[$key] = $value;
	}

	/**
	 * Gets a global
	 * @param  [type] $key [description]
	 * @return [type]      [description]
	 */
	public static function getGlobal($key)
	{
		return self::$globals[$key] ?? null;
	}
}
