<?php
namespace Clockwork\Support\Lumen;

use Illuminate\Support\Facades\Facade as IlluminateFacade;

class Facade extends IlluminateFacade
{
    protected static function getFacadeAccessor() { return 'clockwork'; }
}
