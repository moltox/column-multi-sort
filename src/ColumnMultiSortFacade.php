<?php

namespace Moltox\ColumnMultiSort;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Moltox\ColumnMultiSort\ColumnMultiSort
 */
class ColumnMultiSortFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'column-multi-sort';
    }
}
