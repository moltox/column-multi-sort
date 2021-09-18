<?php

namespace Moltox\ColumnMultiSort;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Moltox\ColumnMultiSort\Exceptions\ColumnMultiSortException;
use Moltox\ColumnMultiSort\Classes\SortQueries;

trait ColumnMultiSort
{

    /**
     * @param $query
     * @param  array  $params
     *
     * Params: array(["column" => "direction", "column" => "direction"] )
     *
     * @return \Illuminate\Database\Query\Builder|mixed
     * @throws ColumnMultiSortException
     */
    public function scopeMultiSort($query, array $params = [])
    {

        $sorts = $this->findParams($params);

        Log::debug('ColumnMultiSort'.print_r($sorts, true));

        if (!empty($sorts)) {

            return $this->buildQueries($query, $sorts);

        }

        return $query;

    }

    /**
     * Attempt to find params with priority:
     * 1. parameters in request
     * 2. parameters as on scope command
     * 3. parameters set as default in model
     *
     * @return array|mixed
     */
    private function findParams($params = [])
    {

        if (request()->has('order')) {

            return request()->get('order');

        }

        if (!empty($params) ) {

            return $params;

        }

        if (isset($this->sortDefault) && !empty($this->sortDefault)) {

            return $this->sortDefault;

        }

    }

    /**
     * @param  Builder  $query
     * @param  array  $sorts
     *
     * @return Builder
     */
    private function buildQueries(Builder $query, array $sorts)
    {

        Log::debug('buildQueries: '.print_r([$query::class, $sorts], true));

        foreach ($sorts as $column => $direction) {
            Log::debug('buildQueries 1: '.print_r([$sorts, $column, $direction], true));
            if ($this->isRelational($column)) {

                $splitted = $this->splitRelated($column);

                $relation = $splitted[0];
                // TODO add handle for multi relation
                $column = $splitted[count($splitted) - 1];

              //  $query = SortQueries::addJoin($query, $relation, $column);

            } else {

                $query->orderBy($column, $direction);

            }



        }

        Log::debug('Query ' . $query->toSql());

        return $query;

    }

    /**
     * @param  string  $column
     *
     * @return bool
     */
    private function isRelational(string $column)
    {

        return count($this->splitRelated($column)) > 1;

    }

    private function splitRelated($relationColumn)
    {

        return explode(config('column-multi-sort.uri_relation_column_separator', '.'), $relationColumn);

    }

}
