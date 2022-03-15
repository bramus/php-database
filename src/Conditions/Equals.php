<?php

namespace Bramus\Database\Conditions;

class Equals extends Base
{
	public function __construct()
	{
		// parent::__construct(...func_get_args());
		call_user_func_array([$this, 'parent::__construct'], func_get_args());

		if ($this->params[0] === null) {
			$this->operator = 'IS';
		} else {
			$this->operator = '=';
		}
	}
}
