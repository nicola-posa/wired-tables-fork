<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace DefStudio\WiredTables\Concerns;

use DefStudio\WiredTables\Elements\Column;
use DefStudio\WiredTables\Enums\Config;
use DefStudio\WiredTables\Enums\Sorting;
use DefStudio\WiredTables\Exceptions\SortingException;
use DefStudio\WiredTables\WiredTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * @mixin WiredTable
 */
trait HasSorting
{
    public array $sorting = [];

    public function supportMultipleSorting(): bool
    {
        return $this->configuration()->get(Config::support_multiple_sorting, false);
    }

    public function sort(string $columnName): void
    {
        $column = $this->getColumn($columnName);

        if ($column === null) {
            throw SortingException::columnNotFound($columnName);
        }

        if (!$column->isSortable()) {
            throw SortingException::columnNotSortable($column->name());
        }

        $direction = $this->getSortDirection($column->name())->next();

        if (!$this->supportMultipleSorting()) {
            $this->sorting = [];
        }

        if ($direction === Sorting::none) {
            unset($this->sorting[$column->name()]);

            return;
        }

        $this->sorting[$column->name()] = $direction->value;
    }

    public function getSortDirection(Column|string $column): Sorting
    {
        $column = is_string($column) ? $column : $column->name();

        return Sorting::tryFrom($this->sorting[$column] ?? null) ?? Sorting::none;
    }

    public function getSortPosition(Column|string $column): int
    {
        $column = is_string($column) ? $column : $column->name();

        if (!array_key_exists($column, $this->sorting)) {
            return 0;
        }


        return array_search($column, array_keys($this->sorting)) + 1;
    }

    protected function applySorting(Builder|Relation $query): void
    {
        $sorting = empty($this->sorting)
            ? $this->config(Config::default_sorting, [])
            : $this->sorting;

        foreach ($sorting as $columnName => $dir) {
            $dir = Sorting::from($dir);

            $column = $this->getColumn($columnName);

            if (empty($column)) {
                return;
            }

            if (!$column->isSortable()) {
                throw SortingException::columnNotSortable($column->name());
            }

            if ($column->hasSortClosure()) {
                $column->applySortClosure($query, $dir);

                return;
            }

            if ($column->isRelation()) {
                $model = $query->getModel();

                if ($column->getRelationNesting() > 1) {
                    throw SortingException::autosortNotSupportedForNestedRelations($column->getRelation());
                }

                $relation = $column->getRelation();

                if (!method_exists($model, $relation)) {
                    throw SortingException::relationDoesntExist($relation);
                }

                $relation = $model->{$relation}();
                match ($relation::class) {
                    BelongsTo::class => $this->applySortingToBelongsTo($query, $column, $relation, $dir),
                    default => throw SortingException::autosortRelationNotSupported($model->{$relation}()::class),
                };

                return;
            }

            $query->orderBy($column->getField(), $dir->value);
        }
    }

    protected function applySortingToBelongsTo(Builder|Relation $query, Column $column, BelongsTo $relation, Sorting $dir): void
    {
        $relatedModel = $relation->getModel();
        $relatedTable = $relatedModel->getTable();
        $foreignKey = $relation->getQualifiedForeignKeyName();

        $query->orderBy(
            DB::table($relatedTable)->select($column->getField())->whereColumn($foreignKey, "$relatedTable.id"),
            $dir->value,
        );
    }

    public function clearSorting(string $columnName): void
    {
        unset($this->sorting[$columnName]);
    }
}
