<?php

namespace NinjaTables\App\Services\Render;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\App\Modules\Integrations\FluentCart\ProductFormatter;
use NinjaTables\Framework\Support\Arr;

/**
 * Rows for DataTables tables whose data comes from a provider (Fluent Forms, …) rather
 * than the per-table dynamic SQL table. The whole set is sent once and DataTables pages,
 * searches and sorts it in the browser.
 */
class DataTablesProviderRows
{
    /**
     * Escaped, DataTables-shaped rows; the signed shortcode-locked filter is applied here
     * so restricted rows never reach the browser. $scope carries query-shaping shortcode
     * attributes (post_tax) to the provider.
     */
    public static function get($tableId, array $settings, array $lockedFilters = [], array $scope = [])
    {
        $maxRows = intval(apply_filters('ninja_tables_public_max_rows', 3000));
        $skip    = max(0, intval(Arr::get($settings, 'skip_rows', 0)));
        $limit   = intval(Arr::get($settings, 'limit_rows', 0));

        if ($limit <= 0 || $limit > $maxRows) {
            $limit = $maxRows;
        }

        // Same server-side own-rows enforcement as the FooTable public feed.
        $ownOnly = (bool) apply_filters('ninja_tables_public_force_own_only', false, $tableId);

        $columns = ninja_table_get_table_columns($tableId, 'public') ?: [];

        // Pro's WP Posts query reads post_tax from this global (set by FooTableRenderer).
        // Set it for this fetch only, so a table rendered earlier on the page can't leak in.
        global $ninja_table_current_rendering_table;
        $previousTable = $ninja_table_current_rendering_table;
        $ninja_table_current_rendering_table = [
            'shortCodeData' => [
                'post_tax'       => (string) Arr::get($scope, 'post_tax', ''),
                'post_tax_field' => (string) Arr::get($scope, 'post_tax_field', 'slug'),
            ],
        ];

        try {
            $rows = ninjaTablesGetTablesDataByID(
                $tableId,
                $columns,
                Arr::get($settings, 'default_sorting'),
                false,
                $limit,
                $skip,
                $ownOnly
            );
        } finally {
            $ninja_table_current_rendering_table = $previousTable;
        }

        $rows = static::applyLockedFilters(is_array($rows) ? $rows : [], $lockedFilters);

        $columnTypeMap     = DataTablesRowFormatter::buildColumnTypeMap($columns);
        $executeShortcodes = ninja_tables_provider_executes_shortcodes($tableId);
        $trustedColumns    = static::trustedHtmlColumns($tableId, $columns);
        $isFluentCart      = ninja_table_get_data_provider($tableId) === 'wp_fct';

        $result  = [];
        $counter = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = Arr::get($row, '___id___');
            unset($row['___id___']);

            $shortcodesRendered = false;
            if ($executeShortcodes) {
                $row                = DataTablesRowFormatter::renderShortcodesInRowValues($row, $columnTypeMap);
                $shortcodesRendered = true;
            }

            $trusted = array_intersect_key($row, $trustedColumns);
            $row     = array_replace(
                DataTablesRowFormatter::escapeRowValues($row, $columnTypeMap, $shortcodesRendered),
                $trusted
            );

            $row['DT_RowClass'] = 'ninja_table_row_' . $counter . ($rowId ? ' nt_row_id_' . intval($rowId) : '');

            // FluentCart rows have no stored id; bulk compare keys rows by index, as on FooTable.
            if (!$rowId && $isFluentCart) {
                $row['DT_RowClass'] .= ' nt_row_id_' . $counter;
            }

            $result[] = $row;
            $counter++;
        }

        return $result;
    }

    /**
     * Column keys whose provider output is sent as-is, like FooTable does. WP Posts
     * "Shortcode / HTML" columns are admin-authored templates whose post/meta/ACF
     * placeholders Pro already escapes; wp_kses_post would strip their iframes/inputs.
     */
    protected static function trustedHtmlColumns($tableId, array $columns)
    {
        $keys     = [];
        $provider = ninja_table_get_data_provider($tableId);
        if ($provider === 'wp-posts') {
            foreach ($columns as $column) {
                if (Arr::get($column, 'source_type') === 'custom' && Arr::get($column, 'wp_post_custom_data_type') === 'shortcode') {
                    $keys[] = (string) Arr::get($column, 'key');
                }
            }
        } elseif ($provider === 'wp_fct') {
            foreach ($columns as $column) {
                if (static::isFluentCartHtmlColumn($column)) {
                    $keys[] = (string) Arr::get($column, 'key');
                }
            }
        }

        $keys = (array) apply_filters('ninja_tables_dt_trusted_html_columns', $keys, $tableId);

        return array_flip(array_filter(array_map('strval', $keys), function ($key) {
            return $key !== '';
        }));
    }

    /**
     * FluentCart cells ProductFormatter builds itself (variation select, quantity input,
     * cart buttons, images, term links); wp_kses_post would strip their form controls.
     * Post meta / post data columns and {post.x}/{postmeta.x} placeholders carry raw
     * stored values and stay escaped.
     */
    protected static function isFluentCartHtmlColumn($column)
    {
        if (Arr::get($column, 'column_type') === 'dynamic_column') {
            $source = Arr::get($column, 'wp_post_custom_data_source_type');

            if ($source === 'shortcode') {
                return !ProductFormatter::fieldPlaceholder(Arr::get($column, 'wp_post_custom_data_value', ''));
            }

            return in_array($source, ['tax_data', 'product_data', 'featured_image'], true);
        }

        return in_array(Arr::get($column, 'key'), [
            'fct_product_image', 'fct_post_title', 'fct_product_category', 'fct_product_price',
            'fct_product_stock', 'fct_product_variations', 'fct_product_quantity', 'fct_product_buy',
        ], true);
    }

    /**
     * In-memory twin of DynamicRow's locked-filter operators (exact, like, starts_with,
     * gt, lt). Fails closed: a row without the filtered column is dropped.
     */
    public static function applyLockedFilters(array $rows, array $filters)
    {
        $filters = array_filter($filters, 'is_array');

        if (!$filters) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $filter) {
                if (!static::matches($row, $filter)) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected static function matches($row, array $filter)
    {
        $column = (string) Arr::get($filter, 'column', '');

        if ($column === '' || !is_array($row) || !array_key_exists($column, $row) || !is_scalar($row[$column])) {
            return false;
        }

        // MySQL's default collation compares case-insensitively; match it.
        $cell   = mb_strtolower(trim(wp_strip_all_tags((string) $row[$column])));
        $needle = mb_strtolower(trim((string) Arr::get($filter, 'value', '')));

        switch (Arr::get($filter, 'operator', 'exact')) {
            case 'exact':
                return $cell === $needle;
            case 'like':
                return $needle === '' || mb_strpos($cell, $needle) !== false;
            case 'starts_with':
                return $needle === '' || mb_strpos($cell, $needle) === 0;
            case 'gt':
                return static::toNumber($cell) > static::toNumber($needle);
            case 'lt':
                return static::toNumber($cell) < static::toNumber($needle);
        }

        return false;
    }

    protected static function toNumber($value)
    {
        return floatval(preg_replace('/[^0-9.\-]/', '', (string) $value));
    }
}
