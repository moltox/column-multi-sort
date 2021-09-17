<?php

namespace Moltox\ColumnMultiSort;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kyslik\ColumnSortable\Exceptions\ColumnMultiSortException;

trait ColumnMultiSort
{

    public function scopeMultiSort($query)
    {

        $this->readParamsFromRequest();

    }

    private function readParamsFromRequest()
    {

        $params = request()->all();


    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $sortParameters
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws ColumnMultiSortException
     */
    private function queryOrderBuilder($query, array $sortParameters)
    {

        /**
         * @var Model $model
         */
        $model = $this;

        list($column, $direction) = $this->parseParameters($sortParameters);

        if (is_null($column)) {
            return $query;
        }

        $relationColumnArray = $this->explodeSortParameter($column);

        if (!empty($relationColumnArray)) {
            $relationName = $relationColumnArray[0];
            $column = $relationColumnArray[1];

            try {

                $relation = $query->getRelation($relationName);

                $query = $this->queryJoinBuilder($query, $relation);

            } catch (BadMethodCallException $e) {
                throw new ColumnMultiSortException($relationName, 1, $e);
            } catch (\Exception $e) {
                throw new ColumnMultiSortException($relationName, 2, $e);
            }

            $model = $relation->getRelated();
        }

        if (isset($model->sortableAs) && in_array($column, $model->sortableAs)) {

            $query = $query->orderBy($column, $direction);

        } elseif ($this->columnExists($model, $column)) {

            $column = $model->getTable().'.'.$column;
            $query = $query->orderBy($column, $direction);

        }

        return $query;
    }


    /**
     * @param  array  $parameters
     *
     * @return array
     */
    private function parseParameters(array $parameters)
    {

        Log::debug('parseParameters'.print_r($parameters, true));

        return [];
    }


    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Relations\BelongsTo|\Illuminate\Database\Eloquent\Relations\HasOne  $relation
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws \Exception
     */
    private function queryJoinBuilder($query, $relation)
    {

        $relatedTable = $relation->getRelated()->getTable();
        $parentTable = $relation->getParent()->getTable();

        if ($parentTable === $relatedTable) {
            $query = $query->from($parentTable.' as parent_'.$parentTable);
            $parentTable = 'parent_'.$parentTable;
            $relation->getParent()->setTable($parentTable);
        }

        if ($relation instanceof HasOne) {
            $relatedPrimaryKey = $relation->getQualifiedForeignKeyName();
            $parentPrimaryKey = $relation->getQualifiedParentKeyName();
        } elseif ($relation instanceof BelongsTo) {
            $relatedPrimaryKey = $relation->getQualifiedOwnerKeyName();
            $parentPrimaryKey = $relation->getQualifiedForeignKeyName();
        } else {
            throw new \Exception();
        }

        return $this->formJoin($query, $parentTable, $relatedTable, $parentPrimaryKey, $relatedPrimaryKey);
    }


    /**
     * @param $model
     * @param $column
     *
     * @return bool
     */
    private function columnExists($model, $column)
    {

        return (isset($model->sortable)) ? in_array($column, $model->sortable) :
            Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
    }


    /**
     * @param  array|string  $array
     *
     * @return array
     */
    private function formatToParameters($array)
    {

        if (empty($array)) {
            return [];
        }

        $defaultDirection = config('column-multi-sort.default_direction', 'asc');

        if (is_string($array)) {
            return ['sort' => $array, 'direction' => $defaultDirection];
        }

        return (key($array) === 0) ? ['sort' => $array[0], 'direction' => $defaultDirection] : [
            'sort' => key($array),
            'direction' => reset($array),
        ];
    }


    /**
     * @param $query
     * @param $parentTable
     * @param $relatedTable
     * @param $parentPrimaryKey
     * @param $relatedPrimaryKey
     *
     * @return mixed
     */
    private function formJoin($query, $parentTable, $relatedTable, $parentPrimaryKey, $relatedPrimaryKey)
    {

        $joinType = config('column-multi-sort.join_type', 'leftJoin');

        return $query->select($parentTable.'.*')
            ->{$joinType}($relatedTable, $parentPrimaryKey, '=', $relatedPrimaryKey);
    }

    private function explodeSortParameter($parameter): array
    {

        $separator = config('column-multi-sort.uri_relation_column_separator', '.');

        if (Str::contains($parameter, $separator)) {

            $oneToOneSort = explode($separator, $parameter);

            if (count($oneToOneSort) !== 2) {

                throw new ColumnMultiSortException();

            }

            return $oneToOneSort;
        }

        return [];
    }

}
