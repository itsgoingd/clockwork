<?php namespace Clockwork\Support\Laravel;

use Illuminate\Support\Facades\Facade as IlluminateFacade;

// Clockwork facade
class Facade extends IlluminateFacade
{
	protected static function getFacadeAccessor() { return 'clockwork'; }
}
