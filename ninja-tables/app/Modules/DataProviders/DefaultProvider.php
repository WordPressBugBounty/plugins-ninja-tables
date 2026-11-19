<?php

namespace NinjaTables\App\Modules\DataProviders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NinjaTables\App\Models\NinjaTableItem;
use NinjaTables\App\Support\DateSort;
use NinjaTables\Framework\Support\Arr;

class DefaultProvider extends BaseProvider
{
    public function key()
    {
        return 'default';
    }

    public function getTableSettings($table)
    {
        $table->isEditable        = true;
        $table->dataSourceType    = 'default';
        $table->isExportable      = true;
        $table->isImportable      = true;
        $table->isSortable        = true;
        $table->isCreatedSortable = true;
        $table->hasCacheFeature   = true;

        return $table;
    }

    public function data($data, $tableId, $defaultSorting, $limit = false, $skip = false, $ownOnly = false)
    {
        $advancedQuery = false;
        $disabledCache = false;

        if ($skip || $limit || $ownOnly) {
            $advancedQuery = true;
        }

        $settings        = ninja_table_get_table_settings($tableId);
        $sortingType     = Arr::get($settings, 'sorting_type', 'by_created_at');
        $sortingColumn   = Arr::get($settings, 'sorting_column');
        $sortingColumnBy = Arr::get($settings, 'sorting_column_by', 'asc');

        // if cached not disabled then return cached data
        if (!$advancedQuery && !$disabledCache = ninja_tables_shouldNotCache($tableId)) {
            $cachedData = get_post_meta($tableId, '_ninja_table_cache_object', true);
            if ($cachedData) {
                return $cachedData;
            }
        }

        $query = NinjaTableItem::where('table_id', $tableId);

        if ($sortingColumn && ($limit || $skip) && $sortingType === 'by_column') {
            $query = $this->orderByJsonQuery($query, $tableId);
        } else {
            if ($defaultSorting == 'new_first') {
                $query->orderBy('created_at', 'desc');
            } elseif ($defaultSorting == 'manual_sort') {
                $query->orderBy('position', 'asc');
            } else {
                $query->orderBy('created_at', 'asc');
            }
        }

        $skip = intval($skip);
        if ($skip && $skip > 0) {
            $query->skip($skip);
        }

        $limit = intval($limit);
        if ($limit && $limit > 0) {
            $query->limit($limit);
        } elseif ($skip && $skip > 0) {
            $maxRows = intval(apply_filters('ninja_tables_public_max_rows', 3000));
            $query->limit($maxRows);
        }

        if ($ownOnly) {
            $query = apply_filters('ninja_table_own_data_filter_query', $query, $tableId);
        }

        $items = $query->get();

        foreach ($items as $item) {
            $values             = json_decode($item->value, true);
            $values['___id___'] = $item->id;
            $data[]             = $values;
        }

        // Please do not hook this filter unless you don't know what you are doing.
        // Hook ninja_tables_get_public_data instead.
        // You should hook this if you need to cache your filter modifications
        $data = apply_filters('ninja_tables_get_raw_table_data', $data, $tableId);

        if (!$advancedQuery && !$disabledCache) {
            update_post_meta($tableId, '_ninja_table_cache_object', $data);
        }

        return $data;
    }

    protected function orderByJsonQuery($query, $tableId)
    {
        $settings        = ninja_table_get_table_settings($tableId);
        $columns         = ninja_table_get_table_columns($tableId);
        $sortingColumn   = Arr::get($settings, 'sorting_column');
        $sortingColumnBy = Arr::get($settings, 'sorting_column_by', 'asc');
        $sortingColumnBy = in_array(strtoupper($sortingColumnBy), ['ASC', 'DESC']) ? $sortingColumnBy : 'DESC';

        $column = Arr::first($columns, function ($column) use ($sortingColumn) {
            return Arr::get($column, 'key') === $sortingColumn;
        });

        if (!$column) {
            return $query;
        }

        $dataType      = Arr::get($column, 'data_type');
        $sortingColumn = sanitize_key(Arr::get($column, 'key'));

        // $sortingColumn went through sanitize_key(), so the inline JSON path is safe
        $value = "JSON_UNQUOTE(JSON_EXTRACT(value, '$.$sortingColumn'))";

        if ($dataType === 'number') {
            // DECIMAL, not SIGNED, so 1.2 and 1.5 don't tie (matches the admin sort)
            $query->orderByRaw("CAST($value AS DECIMAL(30,10)) " . $sortingColumnBy);
        } elseif ($dataType !== 'date' || !DateSort::orderBy($query, $value, [], $sortingColumnBy, $column)) {
            $query->orderByRaw("$value " . $sortingColumnBy);
        }

        return $query;
    }
}
