<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Saves a drag-and-drop order for the reorder pages.
 */
trait SavesManualOrder
{
    /**
     * Number the records of $query 1..n in the order of $order. Keys that
     * are not records of $query are ignored; records missing from $order
     * keep their relative order after the listed ones. The query must be
     * school-scoped, so another school's records are never touched.
     *
     * @param  Builder<Model>  $query
     * @param  array<int|string>  $order
     */
    protected function saveManualOrder(Builder $query, array $order, string $column = 'sort_order'): void
    {
        $current = (clone $query)->orderBy($column)->pluck($query->getModel()->getQualifiedKeyName());
        $listed = collect($order)->map(fn ($key): int => (int) $key)->intersect($current);
        $ordered = $listed->merge($current->diff($listed))->values();

        DB::transaction(function () use ($ordered, $query, $column): void {
            foreach ($ordered as $index => $key) {
                // A plain update, like Filament's own reordering: no activity entry per move.
                (clone $query)->whereKey($key)->update([$column => $index + 1]);
            }
        });
    }
}
