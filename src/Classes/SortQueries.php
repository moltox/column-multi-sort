<?php

namespace Moltox\ColumnMultiSort\Classes;

use Illuminate\Database\Eloquent\Builder;

class SortQueries
{

    /**
     * @param  Builder  $query
     * @param $parentTable
     * @param $relatedTable
     * @param $parentPrimaryKey
     * @param $relatedPrimaryKey
     *
     * @return mixed
     */
    public static function addJoin(Builder $query, $parentTable, $relatedTable, $parentPrimaryKey, $relatedPrimaryKey)
    {

        $joinType = config('column-multi-sort.join_type', 'leftJoin');

        return $query
            ->select($parentTable.'.*')
            ->{$joinType}($relatedTable, $parentPrimaryKey, '=', $relatedPrimaryKey);
    }

}
