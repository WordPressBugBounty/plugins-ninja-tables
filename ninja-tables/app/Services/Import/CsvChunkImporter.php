<?php

namespace NinjaTables\App\Services\Import;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\App\Services\Table\Engine;
use NinjaTables\App\Services\Table\RowWriter;
use NinjaTables\Framework\Support\Arr;
use NinjaTables\Framework\Support\Sanitizer;

/**
 * Large CSV import from the media library, processed in chunks tracked by a transient job.
 * Errors are thrown as ImportException carrying the response the controller should send.
 */
class CsvChunkImporter
{
    const CPT = 'ninja-table';
    const JOB_PREFIX = 'ninja_tables_csv_import_job_';

    /** @var TableImporter */
    protected $tables;

    public function __construct(?TableImporter $tables = null)
    {
        $this->tables = $tables ?: new TableImporter();
    }

    public function init(array $input)
    {
        try {
            $doUnicode = Sanitizer::sanitizeTextField(Arr::get($input, 'do_unicode', 'no'));
            $renderEngine = Sanitizer::sanitizeTextField(Arr::get($input, 'render_engine', 'footable'));
            $renderEngine = in_array($renderEngine, ['footable', 'datatables'], true) ? $renderEngine : 'footable';
            $sourceInfo = $this->resolveCsvImportSource($input);
            if (is_wp_error($sourceInfo)) {
                throw new ImportException([
                    'data' => ['message' => $sourceInfo->get_error_message()]
                ], 422);
            }

            $attachmentId = (int)Arr::get($sourceInfo, 'attachment_id');
            $filePath     = Arr::get($sourceInfo, 'file_path');
            $fileName     = Arr::get($sourceInfo, 'file_name');

            if (!$this->isValidImportPath($filePath)) {
                throw new ImportException([
                    'data' => ['message' => __('Uploaded file path is not valid.', 'ninja-tables')]
                ], 422);
            }

            $fileStats = $this->scanCsvFile($filePath);
            if (is_wp_error($fileStats)) {
                throw new ImportException([
                    'data' => ['message' => $fileStats->get_error_message()]
                ], 422);
            }

            $header          = Arr::get($fileStats, 'header', []);
            $totalRows       = (int)Arr::get($fileStats, 'total_rows', 0);
            $initialCursor   = (int)Arr::get($fileStats, 'cursor_after_header', 0);
            $formattedHeader = ninja_table_format_header($header);

            $tableId = $this->tables->createTable(array(
                'post_title'   => $fileName,
                'post_content' => '',
                'post_type'    => self::CPT,
                'post_status'  => 'publish'
            ));

            $this->tables->storeTableConfigWhenImporting($tableId, $formattedHeader, $renderEngine, 'ajax_table');
            $token = wp_generate_password(32, false, false);
            $jobTtl = $this->getImportJobTtl();
            $job   = [
                'owner_id'       => get_current_user_id(),
                'attachment_id'  => $attachmentId,
                'file_path'      => $filePath,
                'table_id'       => (int)$tableId,
                'header_keys'    => array_keys($formattedHeader),
                'render_engine'  => $renderEngine,
                'do_unicode'     => $doUnicode === 'yes' ? 'yes' : 'no',
                'processed_rows' => 0,
                'total_rows'     => $totalRows,
                'cursor'         => $initialCursor,
                'created_at'     => time()
            ];

            set_transient(self::JOB_PREFIX . $token, $job, $jobTtl);

            return [
                'message' => __('CSV uploaded. Import has started.', 'ninja-tables'),
                'data'    => [
                    'token'      => $token,
                    'tableId'    => (int)$tableId,
                    'total_rows' => $totalRows
                ]
            ];
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

    public function processChunk(array $input)
    {
        try {
            $token            = Sanitizer::sanitizeTextField(Arr::get($input, 'token'));
            $requestedChunk   = intval(Arr::get($input, 'chunk_size', 0));
            $defaultChunkSize = (int)apply_filters('ninja_tables_csv_import_chunk_size', 20000);
            $chunkSize        = $requestedChunk > 0 ? $requestedChunk : $defaultChunkSize;
            $maxChunkSize     = (int)apply_filters('ninja_tables_csv_import_max_chunk_size', 50000);
            $maxChunkSize     = max(100, $maxChunkSize);
            $chunkSize        = max(100, min($chunkSize, $maxChunkSize));

            if (!$token) {
                throw new ImportException([
                    'data' => ['message' => __('Import token is missing.', 'ninja-tables')]
                ], 422);
            }

            $job = get_transient(self::JOB_PREFIX . $token);
            if (!$job || !is_array($job)) {
                throw new ImportException([
                    'data' => ['message' => __('Import session expired. Please start again.', 'ninja-tables')]
                ], 410);
            }

            if ((int)Arr::get($job, 'owner_id') !== get_current_user_id()) {
                throw new ImportException([
                    'data' => ['message' => __('You are not allowed to process this import job.', 'ninja-tables')]
                ], 403);
            }

            $filePath = Arr::get($job, 'file_path');
            if (!$this->isValidImportPath($filePath)) {
                throw new ImportException([
                    'data' => ['message' => __('CSV file is not accessible anymore.', 'ninja-tables')]
                ], 422);
            }

            $headerKeys = Arr::get($job, 'header_keys', []);
            if (empty($headerKeys) || !is_array($headerKeys)) {
                throw new ImportException([
                    'data' => ['message' => __('Import header is not valid.', 'ninja-tables')]
                ], 422);
            }

            $processedRows = (int)Arr::get($job, 'processed_rows', 0);
            $totalRows     = (int)Arr::get($job, 'total_rows', 0);
            $tableId       = (int)Arr::get($job, 'table_id');
            $renderEngine  = Arr::get($job, 'render_engine', 'footable');
            $doUnicode     = Arr::get($job, 'do_unicode') === 'yes';
            $cursor        = (int)Arr::get($job, 'cursor', 0);

            if ($processedRows >= $totalRows) {
                delete_transient(self::JOB_PREFIX . $token);
                ninjaTablesClearTableDataCache($tableId);

                return [
                    'message' => __('Import completed successfully.', 'ninja-tables'),
                    'data'    => [
                        'completed'      => true,
                        'tableId'        => $tableId,
                        'processed_rows' => $processedRows,
                        'total_rows'     => $totalRows
                    ]
                ];
            }

            $chunkResult = $this->readCsvChunk($filePath, $cursor, $chunkSize);
            if (is_wp_error($chunkResult)) {
                throw new ImportException([
                    'data' => ['message' => $chunkResult->get_error_message()]
                ], 422);
            }

            $chunkRows = Arr::get($chunkResult, 'rows', []);
            $nextCursor = (int)Arr::get($chunkResult, 'next_cursor', $cursor);

            $rows = [];
            foreach ($chunkRows as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $item = array_slice(array_pad($item, count($headerKeys), ''), 0, count($headerKeys));

                if ($doUnicode) {
                    $item = array_map(function ($value) {
                        return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1') : $value;
                    }, $item);
                }

                $rows[] = array_combine($headerKeys, $item);
            }

            if ($rows) {
                $engine = $renderEngine === 'datatables' ? Engine::DATATABLES : Engine::FOOTABLE;
                (new RowWriter($tableId, $engine))->insert($rows);
            }

            $processedRows += count($chunkRows);
            $job['processed_rows'] = $processedRows;
            $job['cursor']         = $nextCursor;
            set_transient(self::JOB_PREFIX . $token, $job, $this->getImportJobTtl());

            $completed = $processedRows >= $totalRows || (empty($chunkRows) && $nextCursor <= $cursor);
            if ($completed) {
                delete_transient(self::JOB_PREFIX . $token);
                ninjaTablesClearTableDataCache($tableId);
            }

            return [
                'message' => $completed
                    ? __('Import completed successfully.', 'ninja-tables')
                    : __('Import chunk processed.', 'ninja-tables'),
                'data'    => [
                    'completed'      => $completed,
                    'tableId'        => $tableId,
                    'processed_rows' => $processedRows,
                    'total_rows'     => $totalRows
                ]
            ];
        } catch (\Throwable $throwable) {
            if (!empty($token)) {
                delete_transient(self::JOB_PREFIX . $token);
            }

            throw $throwable;
        }
    }

    private function resolveCsvImportSource(array $input)
    {
        $attachmentId = intval(Arr::get($input, 'attachment_id', 0));
        if ($attachmentId > 0) {
            $attachment = get_post($attachmentId);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return new \WP_Error('invalid_attachment', __('Invalid media file selected.', 'ninja-tables'));
            }

            if (!current_user_can('edit_post', $attachmentId)) {
                return new \WP_Error('forbidden_attachment', __('You do not have permission to use this media file.', 'ninja-tables'));
            }

            $filePath = get_attached_file($attachmentId);
            if (!$filePath || !$this->isValidImportPath($filePath)) {
                return new \WP_Error('invalid_attachment_path', __('Selected media file is not accessible.', 'ninja-tables'));
            }

            $fileName = sanitize_file_name(basename($filePath));
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                return new \WP_Error('invalid_attachment_type', __('Selected media file must be a CSV.', 'ninja-tables'));
            }

            $allowedCsvMimes = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
            $attachmentMime  = (string)get_post_mime_type($attachmentId);
            $checkedMime     = (string)Arr::get(wp_check_filetype($fileName), 'type');
            if (
                ($attachmentMime && !in_array($attachmentMime, $allowedCsvMimes, true)) &&
                ($checkedMime && !in_array($checkedMime, $allowedCsvMimes, true))
            ) {
                return new \WP_Error('invalid_attachment_mime', __('Selected media file is not a valid CSV type.', 'ninja-tables'));
            }

            return [
                'attachment_id' => $attachmentId,
                'file_path'     => $filePath,
                'file_name'     => $fileName
            ];
        }

        return new \WP_Error(
            'missing_media_attachment',
            __('Please select a CSV file from WordPress Media Library for large import.', 'ninja-tables')
        );
    }

    private function scanCsvFile($filePath)
    {
        // WP_Filesystem doesn't support streaming (fgetcsv/ftell/fseek); loading
        // the entire large import CSV into memory would defeat the purpose of chunked import.
        $handle = fopen($filePath, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if (!$handle) {
            return new \WP_Error('csv_open_failed', __('Unable to open CSV file.', 'ninja-tables'));
        }

        $header = fgetcsv($handle);
        if (!is_array($header) || empty($header)) {
            fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return new \WP_Error('csv_header_failed', __('Unable to read CSV header row.', 'ninja-tables'));
        }

        $cursorAfterHeader = ftell($handle);
        $totalRows         = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isCsvRowEmpty($row)) {
                continue;
            }

            $totalRows++;
        }

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        return [
            'header'              => $header,
            'total_rows'          => $totalRows,
            'cursor_after_header' => $cursorAfterHeader > 0 ? (int)$cursorAfterHeader : 0
        ];
    }

    private function readCsvChunk($filePath, $cursor, $chunkSize)
    {
        // Streaming CSV read requires native fopen/fseek/fgetcsv; WP_Filesystem has no equivalent.
        $handle = fopen($filePath, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if (!$handle) {
            return new \WP_Error('csv_read_failed', __('Unable to read CSV file.', 'ninja-tables'));
        }

        if (fseek($handle, max(0, (int)$cursor)) !== 0) {
            fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return new \WP_Error('csv_seek_failed', __('Unable to seek CSV file.', 'ninja-tables'));
        }

        $rows = [];
        while (count($rows) < $chunkSize && ($row = fgetcsv($handle)) !== false) {
            if ($this->isCsvRowEmpty($row)) {
                continue;
            }

            $rows[] = $row;
        }

        $nextCursor = ftell($handle);
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        return [
            'rows'       => $rows,
            'next_cursor'=> $nextCursor > 0 ? (int)$nextCursor : (int)$cursor
        ];
    }

    private function isCsvRowEmpty($row)
    {
        if (!is_array($row) || $row === []) {
            return true;
        }

        foreach ($row as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isValidImportPath($path)
    {
        if (!$path || !file_exists($path)) {
            return false;
        }

        $uploadsDir = wp_upload_dir();
        $baseDir    = realpath(Arr::get($uploadsDir, 'basedir', ''));
        $realPath   = realpath($path);

        if (!$baseDir || !$realPath) {
            return false;
        }

        return strpos($realPath, $baseDir) === 0;
    }

    private function getImportJobTtl()
    {
        $ttl = (int)apply_filters('ninja_tables_csv_import_job_ttl_seconds', 2 * HOUR_IN_SECONDS);
        return max(10 * MINUTE_IN_SECONDS, $ttl);
    }
}
