<?php

namespace NinjaTables\App\Services\Import\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\Framework\Database\Schema;
use NinjaTables\Framework\Support\Arr;

class SupsysticAdapter extends BaseAdapter
{
    // Supsystic tables aren't posts, so "imported into" lives in options, one per
    // source table: <IMPORTED_OPTION>_<supsystic table id> => ninja table id.
    // One row each, so imports finishing at the same time can't overwrite each other.
    const IMPORTED_OPTION = '_ninja_tables_supsystic_imported';

    public function getTables()
    {
        global $wpdb;
        $tables = array();
        try {
            if (Schema::hasTable($wpdb->prefix. 'supsystic_tbl_tables')) {
                $tables = $wpdb->get_results("SELECT id as ID,title as post_title FROM {$wpdb->prefix}supsystic_tbl_tables", \OBJECT); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
        } catch (\Exception $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Supsystic not installed / table absent: return an empty list, not an error.
        }

        $imported = $this->importedMap();

        return array_map(function ($table) use ($imported) {
            return $this->tableEntry((int) $table->ID, $table->post_title, isset($imported[$table->ID]) ? $imported[$table->ID] : 0);
        }, (array) $tables);
    }

    public function migrateTable($tableId)
    {
        try {
            $tableId = (int)$tableId;
            global $wpdb;
            $table = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}supsystic_tbl_tables WHERE id = %d LIMIT 1", $tableId)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            if ( ! $table) {
                return new \WP_Error('broke', __('No Table Found with the selected table', 'ninja-tables'));
            }

            $tableRows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}supsystic_tbl_rows WHERE table_id = %d", $tableId)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

            $rawHeaders  = array_shift($tableRows);
            $raw_header  = @unserialize($rawHeaders->data, ['allowed_classes' => false]);
            $headerCells = Arr::get($raw_header, 'cells', array());

            $headers = array();
            foreach ($headerCells as $header_cell) {
                $headers[] = $this->cellText($header_cell);
            }

            $rows = array();
            foreach ($tableRows as $table_row) {
                $cell      = @unserialize($table_row->data, ['allowed_classes' => false]);
                $rowValues = Arr::get($cell, 'cells', array());
                if ($rowValues) {
                    $rowItem = array();
                    foreach ($rowValues as $row_value) {
                        $rowItem[] = $this->cellText($row_value);
                    }
                    $rows[] = $rowItem;
                }

            }

            $headerRow     = $this->formatHeader($headers);
            $formattedRows = $this->prepareTableRows(array_keys($headerRow), $rows);

            $tableTitle = $table->title . ' (Imported From Supsystic Table)';

            $ninjaTableId = $this->createTable($tableTitle);
            $this->initTableConfiguration($ninjaTableId, $headerRow);
            $this->addRows($ninjaTableId, $formattedRows);

            update_option(self::IMPORTED_OPTION . '_' . $tableId, (int) $ninjaTableId, false);

            return $ninjaTableId;
        } catch (\Throwable $exception) {
            return new \WP_Error('broke', $exception->getMessage());
        }
    }

    /**
     * Every saved link, in one query: [supsystic table id => ninja table id]
     */
    private function importedMap()
    {
        global $wpdb;
        $prefix = self::IMPORTED_OPTION . '_';
        $rows   = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- one indexed lookup instead of an option read per table
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%'
        ));

        $map = array();
        foreach ((array) $rows as $row) {
            $map[(int) substr($row->option_name, strlen($prefix))] = (int) $row->option_value;
        }

        return $map;
    }

    /**
     * The cell's formatted value, else its raw one. Anything but plain text counts as empty.
     */
    private function cellText($cell)
    {
        $value = Arr::get($cell, 'fv', '') ?: Arr::get($cell, 'd', '');

        return is_scalar($value) ? $value : '';
    }
}
