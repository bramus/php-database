<?php

namespace Bramus\Database\Conditions;

class In extends Base
{
	protected $operator = 'IN';
	protected $glue = ',';

	protected $paramgroupStart = '(';
	protected $paramgroupEnd = ')';
}
