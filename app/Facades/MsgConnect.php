<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class MsgConnect extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'msgconnect';
    }
}