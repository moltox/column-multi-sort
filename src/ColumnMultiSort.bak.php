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
use Moltox\ColumnMultiSort\Exceptions\ColumnMultiSortException;

trait ColumnMultiSort
{

    public function scopeMultiSort($query)
    {

        $sorts = $this->findParams();

        Log::debug('ColumnMultiSort'.print_r($sorts, true));

        if (!empty($sorts)) {

            return $this->buildQueries($query, $sorts);

        }

        return $query;

    }


    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $sortColumnParams
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws ColumnMultiSortException
     */
    protected function buildQueries($query, array $sortColumnParams): \Illuminate\Database\Query\Builder
    {

        foreach ($sortColumnParams as $column => $direction) {

            /**
             * @var Model $model
             */
            $model = $this;

            if ($this->isRelational($column)) {

                $relationArray = $this->resolveRelatedColumn($column);

                if (!empty($relationArray)) {

                    $relationName = $relationArray[0];
                    $column = $relationArray[1];

                    try {

                        $relation = $query->getRelation($relationName);

                        // $query = $this->queryJoinBuilder($query, $relation);

                        Log::debug('is relational: '.print_r([$column, $relationArray, $relation], true));

                    } catch (BadMethodCallException $e) {
                        throw new ColumnMultiSortException($relation, 1, $e);
                    } catch (\Exception $e) {
                        throw new ColumnMultiSortException($relation, 2, $e);
                    }

                    $model = $relation->getRelated();
                }


            }

            //   Log::debug("One col:".print_r(['arr' => $sortColumnParams, 'foo' => ['col' => $column, 'dir' => $direction]], true));

            if (isset($model->sortableAs) && in_array($column, $model->sortableAs)) {

                Log::debug('Sortable as col: '.$column);
                $query = $query->orderBy($column, $direction);

            } elseif ($this->isSortableColumn($model, $column)) {

                $column = $model->getTable().'.'.$column;
                Log::debug('Add query for (table.col): '.$column);
                $query = $query->orderBy($column, $direction);

            }
        }


        return $query;

        if (is_null($column)) {
            return $query;
        }


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
    private function isSortableColumn($model, $column)
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

    private function resolveRelatedColumn($relationColumn): array
    {

        $separator = config('column-multi-sort.uri_relation_column_separator', '.');

        if (Str::contains($relationColumn, $separator)) {

            $relation = explode($separator, $relationColumn);

            $relation[0] = Str::camel($relation[0]);

            if (count($relation) !== 2) {

                throw new ColumnMultiSortException();

            }

            return $relation;

        }

        return [];
    }

}
