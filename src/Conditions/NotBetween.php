<?php

namespace Bramus\Database\Conditions;

class NotBetween extends Base
{
	protected $operator = 'NOT BETWEEN';
	protected $glue = ' AND ';
}
