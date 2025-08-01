<?php

namespace Wesleydeveloper\DataProcessor\Facades;

use Illuminate\Support\Facades\Facade;

class DataProcessor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wesleydeveloper\DataProcessor\DataProcessor::class;
    }
}
