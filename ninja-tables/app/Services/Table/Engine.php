<?php

namespace NinjaTables\App\Services\Table;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\Framework\Support\Arr;

/**
 * Which storage/render engine a table uses. The one place that knows the rule
 * "default provider + library=datatables => dynamic table, otherwise the shared items table".
 */
class Engine
{
    const FOOTABLE   = 'footable';
    const DATATABLES = 'datatables';

    public static function of($tableId)
    {
        if (ninja_table_get_data_provider($tableId) !== 'default') {
            return self::FOOTABLE;
        }

        $settings = ninja_table_get_table_settings($tableId, 'public');

        return Arr::get($settings, 'library') === self::DATATABLES ? self::DATATABLES : self::FOOTABLE;
    }

    public static function isDataTables($tableId)
    {
        return self::of($tableId) === self::DATATABLES;
    }

    /**
     * Providers whose rows the DataTables engine can render. Unlike of(), this is
     * about rendering only: non-default providers keep their own storage.
     */
    public static function supportsDataTables($tableId)
    {
        $providers = (array) apply_filters('ninja_tables_dt_supported_providers', ['default', 'fluent-form', 'wp-posts', 'raw_sql', 'wp_fct']);

        return in_array(ninja_table_get_data_provider($tableId), $providers, true);
    }

    /**
     * DataTables rows come from the provider filter, not a dynamic table. Other
     * non-default providers (e.g. Pro's Google Sheet sync) keep their dynamic table.
     */
    public static function rendersFromProvider($tableId)
    {
        return ninja_table_get_data_provider($tableId) !== 'default' && self::supportsDataTables($tableId);
    }
}
