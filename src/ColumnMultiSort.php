<?php

namespace Moltox\ColumnMultiSort;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Moltox\ColumnMultiSort\Exceptions\ColumnMultiSortException;

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

        if (config('column-multi-sort.log_enabled', false)) {

            Log::channel(env('LOG_CHANNEL', 'stack'))
                ->info('[MultiSort] using sorts: '.print_r($sorts, true));

        }

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

        if (!empty($params)) {

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

        foreach ($sorts as $column => $direction) {

            $orderBy = $column;

            if ($this->isColumnSortable($column)) {

                if ($this->isRelational($column)) {

                    $splitted = $this->splitRelated($column);

                    $relationName = $splitted[0];

                    $relationName = $this->handleRelation($query, $relationName);

                    $orderBy = $relationName.'.'.$splitted[count($splitted) - 1];

                }

                $query->orderBy($orderBy, $direction);

                if (config('column-multi-sort.log_enabled', false)) {

                    Log::channel(env('LOG_CHANNEL', 'stack'))->info('[MultiSort] SQL '.$query->toSql());

                }

            } else {

                if (config('column-multi-sort.log_enabled', false)) {

                    Log::channel(env('LOG_CHANNEL', 'stack'))
                        ->warning('[MultiSort] unsortable column permitted: '.$column);

                }

            }

        }


        return $query;

    }

    /**
     * @param  string  $column
     *
     * @return bool
     */
    private function isColumnSortable(string $column): bool
    {

        if (config('column-multi-sort.default.enabled', false)) {
            return true;
        }

        if (isset($this->multiSort) && in_array($column, $this->multiSort)) {
            return true;
        }

        return false;

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

    /**
     * @param  Builder  $query
     * @param  string  $relationName
     *
     * @return string
     * @throws ColumnMultiSortException
     */
    private function handleRelation(Builder $query, string $relationName): string
    {

        try {

            $relation = $query->getRelation(Str::camel($relationName));
            $parentTable = $relation->getParent()->getTable();
            $relatedTable = $relation->getRelated()->getTable();

            if ($relation instanceof HasOne) {

                $relatedPrimaryKey = $relation->getQualifiedForeignKeyName();
                $parentPrimaryKey = $relation->getQualifiedParentKeyName();

            } elseif ($relation instanceof BelongsTo) {

                $relatedPrimaryKey = $relation->getQualifiedOwnerKeyName();
                $parentPrimaryKey = $relation->getQualifiedForeignKeyName();

            } else {

                throw new ColumnMultiSortException('Relation not found or unsupported type');

            }

            $query = $this->addJoin($query, $parentTable, $relatedTable, $parentPrimaryKey, $relatedPrimaryKey);


        } catch (\Exception $e) {

            throw new ColumnMultiSortException($e->getMessage());

        }

        return $relatedTable;

    }

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
            ->addSelect($parentTable.'.*')
            ->{$joinType}($relatedTable, $parentPrimaryKey, '=', $relatedPrimaryKey);
    }

}
