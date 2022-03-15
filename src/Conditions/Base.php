<?php

namespace Bramus\Database\Conditions;

class Base
{
	protected $params;
	protected $glue = '';
	protected $operator = '';
	public $fieldName = '';

	protected $paramgroupStart = '';
	protected $paramgroupEnd = '';

	public function __construct()
	{
		$args = func_get_args();
		if (sizeof($args) == 1 && is_array($args[0])) {
			$this->params = $args[0];
		} else {
			$this->params = $args;
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

				if (!$this->fieldName) {
					throw new \Exception('The fieldName for the condition ' . get_class($this) . ' with params `' . implode('`, `', $this->params) . '` has not been set!');
				}

				return $this->fieldName . ' ' . $this->operator . ' ' . ((sizeof($this->params) === 1 && !$this->glue) ? '?' : $this->paramgroupStart . implode($this->glue, array_fill_keys(array_keys($this->params), '?')) . $this->paramgroupEnd);

			break;

			case 'queryparams':
			case 'queryParams':

				return (array) $this->params;

			break;

			default:

				throw new \Exception('Invalid fieldname “' . $name . '”');

		}
	}
}
