<?php

namespace Bramus\Database\Conditions;

class Between extends Base
{
	protected $operator = 'BETWEEN';
	protected $glue = ' AND ';
}
