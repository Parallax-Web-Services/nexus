<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Nexus
 *
 * @package App\Facades
 * @method static string service()
 * @see \App\Support\Nexus
 */
class Nexus extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'nexus';
    }
}
