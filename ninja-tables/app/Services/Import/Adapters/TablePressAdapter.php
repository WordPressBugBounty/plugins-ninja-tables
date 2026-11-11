<?php

namespace NinjaTables\App\Services\Import\Adapters;

if (!defined('ABSPATH')) {
    exit;
}

class TablePressAdapter extends BaseAdapter
{
    public function getTables()
    {
        $arguments = array(
            'post_type'   => 'tablepress_table',
            'post_status' => 'any',
            'numberposts' => -1
        );

        $tables = get_posts($arguments);

        $formattedTables = array();

        foreach ($tables as $table) {
            $linkedTableId = get_post_meta($table->ID, '_imported_to_ninja_table', true);
            $entry         = $this->tableEntry($table->ID, $table->post_title, $linkedTableId);
            // Older screens read the import state from the title
            if ($entry['ninja_table_id']) {
                $entry['post_title'] .= ' (Already Imported)';
            }
            $formattedTables[] = $entry;
        }

        return $formattedTables;
    }

    public function migrateTable($tableId)
    {
        try {
            $table = get_post($tableId);

            $tableRows = json_decode($table->post_content, true);

            $tableSettings = get_post_meta($table->ID, '_tablepress_table_options', true);

            $tableSettings = json_decode($tableSettings, true);

            if ($tableSettings['table_head']) {
                $headerRow = array_values(array_shift($tableRows));

                $headerRow = $this->formatHeader($headerRow);
            } else {
                $headerRow = array();

                $columnCount = count(array_pop(array_reverse($tableRows)));

                for ($i = 0; $i < $columnCount; $i++) {
                    $headerName            = 'Ninja Column ' . ($i + 1);
                    $headerKey             = 'ninja_column_' . ($i + 1);
                    $headerRow[$headerKey] = $headerName;
                }
            }

            $formattedRows = $this->prepareTableRows(array_keys($headerRow), $tableRows);
//            $formattedRows = array_reverse($formattedRows);

            $tableTitle = $table->post_title . ' (Imported From Table Press)';

            $ninjaTableId = $this->createTable($tableTitle);
            $this->initTableConfiguration($ninjaTableId, $headerRow);
            $this->addRows($ninjaTableId, $formattedRows);
            update_post_meta($tableId, '_imported_to_ninja_table', $ninjaTableId);

            return $ninjaTableId;
        } catch (\Throwable $exception) {
            return new \WP_Error('broke', $exception->getMessage());
        }
    }
}
