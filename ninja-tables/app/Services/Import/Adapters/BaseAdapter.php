<?php

namespace NinjaTables\App\Services\Import\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base for importers that pull tables out of other table plugins.
 */
use NinjaTables\App\Modules\DataTables\Database\DynamicTableManager;
use NinjaTables\App\Services\Table\Engine;
use NinjaTables\App\Services\Table\RowWriter;

abstract class BaseAdapter
{

    protected $cpt_name = 'ninja-table';

    // Engine the imported table is created with (footable | datatables)
    protected $engine = Engine::FOOTABLE;

    /**
     * @param string $engine footable | datatables; anything else falls back to footable
     *
     * @return $this
     */
    public function setEngine($engine)
    {
        $this->engine = $engine === Engine::DATATABLES ? Engine::DATATABLES : Engine::FOOTABLE;

        return $this;
    }

    abstract function getTables();

    abstract function migrateTable($tableId);

    /**
     * Create  a table post type
     *
     * @param string $title
     * @param string $description
     *
     * @return int|WP_Error
     */
    public function createTable($title = '', $description = '')
    {
        if ( ! $title) {
            $title = __('Temporary table name', 'ninja-tables');
        }
        if ( ! $description) {
            $description = __('Temporary table description',
                'ninja-tables');
        }

        return wp_insert_post(array(
            'post_title'   => $title,
            'post_content' => $description,
            'post_type'    => $this->cpt_name,
            'post_status'  => 'publish'
        ));
    }

    /**
     * The Ninja table a source table was imported into, if it still exists.
     *
     * @param int|string $ninjaTableId
     *
     * @return int 0 when nothing was imported or the table is gone/trashed
     */
    protected function liveTableId($ninjaTableId)
    {
        $ninjaTableId = (int) $ninjaTableId;
        if (!$ninjaTableId || get_post_type($ninjaTableId) !== $this->cpt_name) {
            return 0;
        }

        return get_post_status($ninjaTableId) === 'trash' ? 0 : $ninjaTableId;
    }

    /**
     * One row of the "tables in another plugin" list.
     */
    protected function tableEntry($id, $title, $linkedTableId)
    {
        $liveId = $this->liveTableId($linkedTableId);

        return array(
            'ID'             => $id,
            'post_title'     => $title,
            'ninja_table_id' => $liveId ?: false,
            // Imported once, but that Ninja table was deleted since
            'import_stale'   => !$liveId && (int) $linkedTableId > 0,
        );
    }

    public function addRows($tableId, $rows)
    {
        (new RowWriter($tableId, $this->engine))->insert($rows);
    }

    public function initTableConfiguration($tableId, $headers)
    {
        // ninja_table_columns
        $ninjaTableColumns = array();
        foreach ($headers as $key => $name) {
            $ninjaTableColumns[] = array(
                'key'         => $key,
                'name'        => $name,
                'breakpoints' => '',
                'data_type'   => 'text'
            );
        }
        update_post_meta($tableId, '_ninja_table_columns',
            $ninjaTableColumns);

        // ninja_table_settings
        // Same settings a file import writes (TableImporter::storeTableConfigWhenImporting)
        $ninjaTableSettings                = ninja_table_get_table_settings($tableId, 'admin');
        $ninjaTableSettings['library']     = $this->engine;
        $ninjaTableSettings['render_type'] = 'legacy_table';
        $ninjaTableSettings                = ninja_tables_normalize_dt_css_classes($ninjaTableSettings);
        update_post_meta($tableId, '_ninja_table_settings', $ninjaTableSettings);
        ninjaTablesClearTableDataCache($tableId);
    }


    /**
     * Create Table Header as an array where array keys will be sanitized and formatted column keys
     *
     * @param $header array
     *
     * @return array
     */
    public function formatHeader($header)
    {
        $data           = array();
        $column_counter = 1;
        $dataTables     = $this->engine === Engine::DATATABLES;
        // DataTables keys become SQL column names (truncated to 64 chars), so two
        // keys must stay distinct as column names, not just as array keys.
        $tableManager = $dataTables ? new DynamicTableManager(0) : null;
        $columnNames  = array();
        foreach ($header as $item) {
            $item = trim(wp_strip_all_tags($item));

            // We'll slugify only if item is printable characters.
            // Otherwise we'll generate custom key for the item.
            // Printable chars as in ASCII printable chars.
            // Ref: http://www.catonmat.net/blog/my-favorite-regex/

            $key = preg_replace('/[^A-Za-z0-9]+/', '', $item);

            $key = sanitize_title($key, 'ninja_column_' . $column_counter, 'display');

            if ($dataTables) {
                // Leave room under the 64-char column limit for "col_" and a "_N" suffix
                $key = substr($key, 0, 50);

                // DataTables stores columns as real DB columns; "id", "position"... are taken
                if (DynamicTableManager::isReservedColumnKey($key)) {
                    $key .= '_col';
                }

                $base    = $key;
                $counter = 1;
                while (isset($data[$key]) || isset($columnNames[$tableManager->sanitizeColumnName($key)])) {
                    $key = $base . '_' . $counter;
                    $counter++;
                }
                $columnNames[$tableManager->sanitizeColumnName($key)] = true;
            } else {
                $counter = 1;
                while (isset($data[$key])) {
                    $key .= '_' . $counter;
                    $counter++;
                }
            }
            $data[$key] = $item;

            $column_counter++;
        }

        return $data;
    }

    /**
     * Prepare the table Rows combining with $headerKeys
     *
     * @param $headerKeys array
     * @param $rows array
     *
     * @return array
     */
    public function prepareTableRows($headerKeys, $rows)
    {
        $formattedRows = array();
        foreach ($rows as $row) {
            if (count($headerKeys) == count($row)) {
                $formattedRows[] = array_combine($headerKeys, $row);
            }
        }

        return $formattedRows;
    }
}
