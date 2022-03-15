<?php

namespace Bramus\Database\Expressions;

class Base
{
	protected $keyword = '';
	public $fieldName = ''; // Not needed for this expression itself, but perhaps for a child condition

	public function __construct()
	{
		$args = func_get_args();
		if (sizeof($args) == 1 && is_array($args[0])) {
			$this->parts = $args[0];
		} else {
			$this->parts = $args;
		}
	}

	public function __set($name, $value)
	{
		$this->$name = $value;
	}

	public function __get($name)
	{
		switch ($name) {

			case 'querypart':
			case 'queryPart':

				// Only 1?
				if (sizeof($this->parts) == 1) {
					return $this->parts[0]->queryPart;
				}

				// More than 1
				$queryParts = [];
				foreach ($this->parts as $fieldName => $part) {
					if (!is_a($part, get_class($this))) {
						if (is_int($fieldName)) {
							$part->fieldName = $this->fieldName;
						} else {
							$part->fieldName = $fieldName;
						}
					}
					$queryParts[] = $part->queryPart;
				}

				return '((' . implode(') ' . $this->keyword . ' (', $queryParts) . '))';

			break;

			case 'queryparams':
			case 'queryParams':

				// Only 1?
				if (sizeof($this->parts) == 1) {
					return (array) $this->parts[0]->queryParams;
				}

				// More than 1
				$queryParams = [];
				foreach ($this->parts as $part) {
					$queryParams = array_merge($queryParams, (array) $part->queryParams);
				}

				return (array) $queryParams;

			break;

			default:

				throw new \Exception('Invalid fieldname “' . $name . '”');

		}
	}
}
