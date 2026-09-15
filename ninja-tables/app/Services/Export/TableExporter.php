<?php

namespace NinjaTables\App\Services\Export;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\App\Library\Csv\Writer;
use NinjaTables\App\Models\NinjaTableItem;
use NinjaTables\App\Services\Table\Engine;
use NinjaTables\App\Services\Table\RowReader;
use NinjaTables\Framework\Support\Arr;
use NinjaTables\Framework\Support\Sanitizer;

/**
 * Builds and streams table exports (CSV/JSON) for both the default and drag & drop tables.
 * Permission and nonce checks live in the ExportHandler entry points.
 */
class TableExporter
{
    public static function export($tableId, $format, $externalSource = false)
    {

        $tableTitle = get_the_title($tableId);

        $fileName = sanitize_title($tableTitle, 'Export-Table-' . gmdate('Y-m-d-H-i-s'), 'preview');

        $tableColumns = ninja_table_get_table_columns($tableId, 'admin');

        $tableSettings = ninja_table_get_table_settings($tableId, 'admin');

        // Check if this is a DataTables table
        $dataProvider = ninja_table_get_data_provider($tableId);
        $isDataTables = Engine::isDataTables($tableId);

        // Only stream tables large enough that building the whole dataset in
        // memory would risk OOM. Smaller tables keep the exact provider path
        // below, so their row order and the provider hook chain are unchanged.
        $streamThreshold = (int) apply_filters('ninja_tables_export_stream_threshold', 20000, $tableId);
        $shouldStream    = !$externalSource
                           && $dataProvider === 'default'
                           && (new RowReader($tableId))->count() >= $streamThreshold;

        if ($shouldStream) {
            if ($format == 'csv') {
                static::streamCsv($tableId, $tableColumns, $tableSettings, $isDataTables, $fileName);
                return;
            } elseif ($format == 'json') {
                static::streamJson($tableId, $tableColumns, $tableSettings, $dataProvider, $isDataTables, $fileName);
                return;
            }
        }

        if ($format == 'csv') {
            if ($isDataTables) {
                $data = (new RowReader($tableId, Engine::DATATABLES))->values();
            } else {
                $sortingType = Arr::get($tableSettings, 'sorting_type', 'by_created_at');
                $data = ninjaTablesGetTablesDataByID($tableId, $tableColumns, $sortingType, true);
            }

            $header = array();

            foreach ($tableColumns as $item) {
                $header[$item['key']] = $item['name'];
            }

            $exportData = array();

            foreach ($data as $item) {
                $temp = array();
                foreach ($header as $accessor => $name) {
                    $value = Arr::get($item, $accessor);
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $temp[] = ninjaTablesSanitizeForCSV($value);
                }
                array_push($exportData, $temp);
            }

            static::exportAsCSV($exportData, $fileName, array_values($header));
        } elseif ($format == 'json') {
            $table = get_post($tableId);
            $rows  = array();

            if ($isDataTables) {
                $rows = (new RowReader($tableId, Engine::DATATABLES))->all();
            } elseif ($dataProvider == 'default') {
                $rawRows = NinjaTableItem::selectedRows($tableId);

                foreach ($rawRows as $row) {
                    $row->value = json_decode($row->value, true);
                    $rows[]     = $row;
                }
            }

            $matas   = get_post_meta($tableId);
            $allMeta = array();

            $excludedMetaKeys = array(
                '_ninja_table_cache_object',
                '_ninja_table_cache_html',
                '_external_cached_data',
                '_last_external_cached_time',
                '_last_edited_by',
                '_last_edited_time',
                '__ninja_cached_table_html'
            );

            foreach ($matas as $metaKey => $metaValue) {
                if (!in_array($metaKey, $excludedMetaKeys)) {
                    if (isset($metaValue[0])) {
                        $metaValue         = maybe_unserialize($metaValue[0]);
                        $allMeta[$metaKey] = $metaValue;
                    }
                }
            }

            $exportData = array(
                'post'          => $table,
                'columns'       => $tableColumns,
                'settings'      => $tableSettings,
                'data_provider' => $dataProvider,
                'metas'         => $allMeta,
                'rows'          => array(),
                'original_rows' => $rows
            );

            if ($externalSource) {
                return $exportData;
            }
            static::exportAsJSON($exportData, $fileName);
        }
    }

    public static function exportCSV($tableData, $fileName = null)
    {
        $rows = [];
        foreach ($tableData['data'] as $row) {
            $cols = [];
            foreach ($row['rows'] as $columns) {
                $values = '';
                foreach ($columns['columns'] as $key => $item) {
                    if (is_array($item['data']['value'])) {
                        $tmp = [];
                        foreach ($item['data']['value'] as $value) {
                            $tmp[] = ninjaTablesSanitizeForCSV($value);
                        }

                        $values .= implode(",", $tmp);
                    } else {
                        $values .= " " . ninjaTablesSanitizeForCSV($item['data']['value']);
                    }
                }
                $cols[] = $values;
            }
            $rows[] = $cols;
        }

        static::exportAsCSV($rows, $fileName);
    }

    public static function exportJSON($tableId, $fileName = null)
    {
        $table_settings   = get_post_meta($tableId, '_ninja_table_builder_table_settings', true);
        $table_responsive = get_post_meta($tableId, '_ninja_table_builder_table_responsive', true);
        $table_data       = get_post_meta($tableId, '_ninja_table_builder_table_data', true);
        $table_html       = get_post_meta($tableId, '_ninja_table_builder_table_html', true);
        $data             = [
            'table_id'         => $tableId,
            'table_name'       => $fileName,
            'table_settings'   => $table_settings,
            'table_responsive' => $table_responsive,
            'table_data'       => $table_data,
            'table_html'       => $table_html
        ];

        static::exportAsJSON($data, $fileName);
    }


    /**
     * Prepare the request to stream a download: drop any output buffers, lift
     * the time limit and send no-cache headers. Callers must send their own
     * Content-Type/Content-Disposition before the first echo.
     */
    private static function prepareStream()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- streamed export must not time out
        }

        nocache_headers();
        header('X-Content-Type-Options: nosniff', true);
    }

    /**
     * Stream a CSV download, paging the rows in chunks so memory stays bounded
     * regardless of table size.
     *
     * @param int    $tableId
     * @param array  $tableColumns
     * @param array  $tableSettings
     * @param bool   $isDataTables
     * @param string $fileName
     */
    private static function streamCsv($tableId, $tableColumns, $tableSettings, $isDataTables, $fileName)
    {
        header('Content-Type: text/csv; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $fileName . '.csv"', true);
        static::prepareStream();

        $buffer = fopen('php://memory', 'r+'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream for fputcsv encoding, not a filesystem file

        // Header row from column names (kept unsanitized, matching the previous
        // exporter which passed array_values($header) raw).
        $header = array();
        foreach ($tableColumns as $item) {
            $header[$item['key']] = $item['name'];
        }
        static::emitCsvLine($buffer, array_values($header));

        $flushEvery = ninjaTablePerChunk($tableId);
        $counter    = 0;

        foreach ((new RowReader($tableId))->each() as $streamRow) {
            $assocRow = $streamRow->value;
            $cells = array();
            foreach ($header as $accessor => $name) {
                $value = Arr::get($assocRow, $accessor);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $cells[] = ninjaTablesSanitizeForCSV($value);
            }
            static::emitCsvLine($buffer, $cells);

            if (++$counter % $flushEvery === 0) {
                if (function_exists('ob_flush')) {
                    @ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                }
                flush();
                if (connection_aborted()) {
                    break;
                }
            }
        }

        fclose($buffer); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the in-memory buffer above
        wp_die();
    }

    /**
     * Encode one CSV row and echo it, preserving the previous exporter's
     * \r\n line ending (fputcsv only emits \n).
     *
     * @param resource $buffer A reusable php://memory handle.
     * @param array    $row
     */
    private static function emitCsvLine($buffer, $row)
    {
        ftruncate($buffer, 0);
        rewind($buffer);
        // Pass enclosure/escape explicitly: matches the previous League\Csv
        // defaults and avoids the PHP 8.4 deprecation for the escape argument.
        fputcsv($buffer, $row, ',', '"', '\\');
        rewind($buffer);
        $line = stream_get_contents($buffer);

        echo rtrim($line, "\n") . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Stream a JSON download. The small envelope (post, columns, settings,
     * metas) is written once, then original_rows is streamed in chunks so the
     * whole dataset is never held in memory.
     *
     * @param int    $tableId
     * @param array  $tableColumns
     * @param array  $tableSettings
     * @param string $dataProvider
     * @param bool   $isDataTables
     * @param string $fileName
     */
    private static function streamJson($tableId, $tableColumns, $tableSettings, $dataProvider, $isDataTables, $fileName)
    {
        header('Content-Type: application/json; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $fileName . '.json"', true);
        static::prepareStream();

        $envelope = array(
            'post'          => get_post($tableId),
            'columns'       => $tableColumns,
            'settings'      => $tableSettings,
            'data_provider' => $dataProvider,
            'metas'         => static::exportableMetas($tableId),
            'rows'          => array(),
        );

        // Emit the envelope minus its closing brace, then append the streamed
        // original_rows array. original_rows is guaranteed absent from the
        // envelope, so appending a fresh key is always valid JSON.
        $prefix = json_encode($envelope);
        echo substr($prefix, 0, -1) . ',"original_rows":['; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $flushEvery = ninjaTablePerChunk($tableId);
        $counter    = 0;
        $first      = true;

        foreach ((new RowReader($tableId))->each() as $rowObj) {
            if (!$isDataTables) {
                unset($rowObj->id); // legacy JSON export never carried the row id
            }
            echo ($first ? '' : ',') . json_encode($rowObj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $first = false;

            if (++$counter % $flushEvery === 0) {
                if (function_exists('ob_flush')) {
                    @ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                }
                flush();
                if (connection_aborted()) {
                    break;
                }
            }
        }

        echo ']}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die();
    }

    /**
     * Build the exportable post-meta map, mirroring the excluded keys used by
     * the non-streamed JSON export.
     *
     * @param int $tableId
     * @return array
     */
    private static function exportableMetas($tableId)
    {
        $excludedMetaKeys = array(
            '_ninja_table_cache_object',
            '_ninja_table_cache_html',
            '_external_cached_data',
            '_last_external_cached_time',
            '_last_edited_by',
            '_last_edited_time',
            '__ninja_cached_table_html'
        );

        $allMeta = array();
        foreach (get_post_meta($tableId) as $metaKey => $metaValue) {
            if (!in_array($metaKey, $excludedMetaKeys) && isset($metaValue[0])) {
                $allMeta[$metaKey] = maybe_unserialize($metaValue[0]);
            }
        }

        return $allMeta;
    }


    private static function exportAsCSV($data, $fileName = null, $header = null)
    {
        $fileName = ($fileName) ? $fileName . '.csv' : 'export-data-' . gmdate('d-m-Y') . '.csv';

        $writer = Writer::createFromFileObject(new \SplTempFileObject());
        $writer->setDelimiter(",");
        $writer->setNewline("\r\n");
        $header !== null ? $writer->insertOne($header) : '';
        $writer->insertAll($data);
        $writer->output($fileName);
        wp_die();
    }

    private static function exportAsJSON($data, $fileName = null)
    {
        $fileName = ($fileName) ? $fileName . '.json' : 'export-data-' . gmdate('d-m-Y') . '.json';

        header('Content-disposition: attachment; filename=' . $fileName);
        header('Content-type: application/json');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($data);

        wp_die();
    }


}
