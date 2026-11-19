<?php

namespace NinjaTables\App\Modules\DataTables\Models;

use NinjaTables\App\Models\Model;
use NinjaTables\App\Modules\DataTables\Database\DynamicTableManager;
use NinjaTables\App\Modules\DataTables\Query\NumericFilter;
use NinjaTables\App\Support\DateSort;
use NinjaTables\Framework\Support\Arr;

class DynamicRow
{
    private $restrictOwnerId = null;
    protected $tableId;

    protected $tableManager;

    protected $columns;

    /**
     * Rows the last batchInsert() could not store: [['index' => int, 'error' => string], ...].
     * 'index' is the row's 0-based position in the $rows passed in.
     *
     * @var array
     */
    protected $failedRows = [];

    public function __construct($tableId)
    {
        $this->tableId      = intval($tableId);
        $this->tableManager = new DynamicTableManager($tableId);
        $this->columns      = get_post_meta($tableId, '_ninja_table_columns', true) ?: [];
    }

    /**
     * When set to an owner id, getAll() and count() return only that owner's
     * rows. Used to enforce a table's "own data only" setting on the public
     * feed; pass -1 for an anonymous viewer so nothing matches.
     *
     * @param int|null $ownerId
     * @return $this
     */
    public function restrictToOwner($ownerId)
    {
        $this->restrictOwnerId = ($ownerId === null) ? null : (int) $ownerId;

        return $this;
    }

    private function applyOwnerScope($query)
    {
        if ($this->restrictOwnerId !== null) {
            $query->where(DynamicTableManager::COL_OWNER_ID, $this->restrictOwnerId);
        }

        return $query;
    }

    public function getTableManager()
    {
        return $this->tableManager;
    }

    public function newQuery()
    {
        return Model::resolveConnection()->table('ninja_tables_dt_' . $this->tableId);
    }

    public function tableExists()
    {
        return $this->tableManager->tableExists();
    }

    public function insert($values, $settings = [], $position = null, $ownerId = null)
    {
        if (!$this->tableExists()) {
            return false;
        }

        if ($position === null) {
            $position = $this->getNextPosition();
        }

        $data = [
            DynamicTableManager::COL_POSITION => $position,
            DynamicTableManager::COL_OWNER_ID => $ownerId ?: get_current_user_id(),
            DynamicTableManager::COL_SETTINGS => !empty($settings) ? maybe_serialize($settings) : null,
        ];

        $existingColumns = $this->tableManager->getExistingColumns();
        $systemColumns   = DynamicTableManager::getSystemColumns();
        foreach ($this->columns as $column) {
            $key          = Arr::get($column, 'key', '');
            $sanitizedKey = $this->tableManager->sanitizeColumnName($key);

            if (in_array($sanitizedKey, $systemColumns)) {
                continue;
            }

            if (in_array($sanitizedKey, $existingColumns) && isset($values[$key])) {
                $data[$sanitizedKey] = is_array($values[$key]) ? json_encode($values[$key]) : $values[$key];
            }
        }

        return $this->newQuery()->insertGetId($data);
    }

    public function update($rowId, $values, $settings = null)
    {
        if (!$this->tableExists()) {
            return false;
        }

        $data = [];

        if ($settings !== null) {
            $data[DynamicTableManager::COL_SETTINGS] = !empty($settings) ? maybe_serialize($settings) : null;
        }

        $existingColumns = $this->tableManager->getExistingColumns();
        foreach ($this->columns as $column) {
            $key          = Arr::get($column, 'key', '');
            $sanitizedKey = $this->tableManager->sanitizeColumnName($key);

            if (in_array($sanitizedKey, $existingColumns) && array_key_exists($key, $values)) {
                $data[$sanitizedKey] = is_array($values[$key]) ? json_encode($values[$key]) : $values[$key];
            }
        }

        if (empty($data)) {
            return true;
        }

        return $this->newQuery()->where(DynamicTableManager::COL_ID, $rowId)->update($data) !== false;
    }

    public function delete($rowId)
    {
        if (!$this->tableExists()) {
            return false;
        }

        return $this->newQuery()->where(DynamicTableManager::COL_ID, $rowId)->delete() !== false;
    }

    public function deleteMany($rowIds)
    {
        if (!$this->tableExists() || empty($rowIds)) {
            return false;
        }

        $ids = array_map('intval', $rowIds);

        return $this->newQuery()->whereIn(DynamicTableManager::COL_ID, $ids)->delete() !== false;
    }

    public function find($rowId)
    {
        if (!$this->tableExists()) {
            return null;
        }

        return $this->newQuery()->where(DynamicTableManager::COL_ID, $rowId)->first();
    }

    public function getAll($perPage = 20, $page = 1, $orderBy = null, $order = 'ASC', $search = null, $customFilters = [])
    {
        if (!$this->tableExists()) {
            return [];
        }

        if ($orderBy === null) {
            $orderBy = DynamicTableManager::COL_POSITION;
        }

        $offset = ($page - 1) * $perPage;

        $allowedOrderBy = array_merge(
            [DynamicTableManager::COL_ID, DynamicTableManager::COL_POSITION, DynamicTableManager::COL_CREATED_AT, DynamicTableManager::COL_UPDATED_AT],
            $this->getColumnKeys()
        );
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : DynamicTableManager::COL_POSITION;
        $order          = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $query = $this->newQuery();

        $this->applySearchConditions($query, $search);
        $this->applyCustomFilters($query, $customFilters);
        $this->applyOwnerScope($query);

        $column = Arr::first($this->columns, function ($column) use ($orderBy) {
            return $this->tableManager->sanitizeColumnName(Arr::get($column, 'key', '')) === $orderBy;
        });

        // Cells are stored as text, so date and number columns need their own ordering
        $type   = Arr::get($column, 'data_type');
        $sorted = $type === 'date' && DateSort::orderBy($query, "`{$orderBy}`", [], $order, $column);
        if (!$sorted && $type === 'number') {
            // Non-numeric text casts to 0; the raw value keeps those in text order
            $query->orderByRaw(NumericFilter::castExpression($orderBy, $column) . " {$order}")
                  ->orderBy($orderBy, $order);
            $sorted = true;
        }
        if (!$sorted) {
            $query->orderBy($orderBy, $order);
        }

        // Stable tie-breaker so offset paging can't drop/duplicate rows that
        // share the ordering value (e.g. duplicate __position).
        if ($orderBy !== DynamicTableManager::COL_ID) {
            $query->orderBy(DynamicTableManager::COL_ID, 'ASC');
        }

        $results = $query->offset($offset)
                        ->limit($perPage)
                        ->get();

        return is_array($results) ? $results : $results->toArray();
    }

    public function count($search = null, $customFilters = [])
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $query = $this->newQuery();

        $this->applySearchConditions($query, $search);
        $this->applyCustomFilters($query, $customFilters);
        $this->applyOwnerScope($query);

        return (int) $query->count();
    }

    public function getDistinctValues($columnKey, $search = null, $customFilters = [])
    {
        if (!$this->tableExists()) {
            return [];
        }

        $colName = $this->tableManager->sanitizeColumnName($columnKey);
        if (!in_array($colName, $this->tableManager->getExistingColumns(), true)) {
            return [];
        }

        $query = $this->newQuery();

        $this->applySearchConditions($query, $search);
        $this->applyCustomFilters($query, $customFilters);
        $this->applyOwnerScope($query);

        $cap     = apply_filters('ninja_tables_dt_max_filter_options', 2000);
        $results = $query->select($colName)->distinct()->limit($cap)->get();

        $values = [];
        foreach ($results as $row) {
            $val = is_object($row)
                ? (isset($row->{$colName}) ? $row->{$colName} : null)
                : Arr::get((array) $row, $colName);
            if ($val !== null && $val !== '') {
                $values[] = $val;
            }
        }

        return array_values(array_unique($values));
    }

    public function getNextPosition()
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $maxPosition = $this->newQuery()->max(DynamicTableManager::COL_POSITION);

        return ($maxPosition !== null) ? $maxPosition + 1 : 0;
    }

    public function updatePosition($rowId, $newPosition)
    {
        if (!$this->tableExists()) {
            return false;
        }

        return $this->newQuery()->where(DynamicTableManager::COL_ID, $rowId)->update([DynamicTableManager::COL_POSITION => $newPosition]) !== false;
    }

    public function mapRowToUserKeys($row)
    {
        $values = [];

        $colId        = DynamicTableManager::COL_ID;
        $colPosition  = DynamicTableManager::COL_POSITION;
        $colOwnerId   = DynamicTableManager::COL_OWNER_ID;
        $colSettings  = DynamicTableManager::COL_SETTINGS;
        $colCreatedAt = DynamicTableManager::COL_CREATED_AT;
        $colUpdatedAt = DynamicTableManager::COL_UPDATED_AT;

        foreach ($this->columns as $column) {
            $key          = Arr::get($column, 'key', '');
            $sanitizedKey = $this->tableManager->sanitizeColumnName($key);

            if (isset($row->{$sanitizedKey})) {
                $raw = $row->{$sanitizedKey};

                if (is_string($raw) && strlen($raw) > 1) {
                    $firstChar = $raw[0];
                    if ($firstChar === '[' || $firstChar === '{') {
                        $decoded = json_decode($raw, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $raw = $decoded;
                        }
                    }
                }

                $values[$key] = $raw;
            } else {
                $values[$key] = '';
            }
        }

        $settings = [];
        if (!empty($row->{$colSettings})) {
            if (is_serialized($row->{$colSettings})) {
                $settings = unserialize($row->{$colSettings}, ['allowed_classes' => false]);
            } else {
                $settings = $row->{$colSettings};
            }
            if (!is_array($settings)) {
                $settings = [];
            }
        }

        return [
            'id'         => $row->{$colId},
            'position'   => $row->{$colPosition},
            'values'     => $values,
            'settings'   => $settings,
            'owner_id'   => $row->{$colOwnerId},
            'created_at' => $row->{$colCreatedAt},
            'updated_at' => $row->{$colUpdatedAt},
        ];
    }

    protected function getColumnKeys()
    {
        $keys = [];
        foreach ($this->columns as $column) {
            $keys[] = $this->tableManager->sanitizeColumnName(Arr::get($column, 'key', ''));
        }

        return $keys;
    }

    protected function applySearchConditions($query, $search)
    {
        if (!$search) {
            return;
        }

        $existingColumns = $this->tableManager->getExistingColumns();
        $searchColumns   = [];

        foreach ($this->columns as $column) {
            if (Arr::get($column, 'unfilterable') === 'yes') {
                continue;
            }

            $sanitizedKey = $this->tableManager->sanitizeColumnName(Arr::get($column, 'key', ''));
            if (in_array($sanitizedKey, $existingColumns)) {
                $searchColumns[] = $sanitizedKey;
            }
        }

        if (empty($searchColumns)) {
            return;
        }

        global $wpdb;
        $escapedSearch = $wpdb->esc_like($search);
        $query->where(function ($q) use ($searchColumns, $escapedSearch) {
            foreach ($searchColumns as $col) {
                $q->orWhere($col, 'LIKE', '%' . $escapedSearch . '%');
            }
        });
    }

    /**
     * Each filter may target multiple columns (OR'd within the filter).
     * Different filters are AND'd together.
     */
    protected function applyCustomFilters($query, $filters)
    {
        if (empty($filters) || !is_array($filters)) {
            return;
        }

        // Limit the number of filters to prevent abuse
        $filters = array_slice($filters, 0, 20);

        $existingColumns = $this->tableManager->getExistingColumns();
        $allowedOperators = ['like', 'exact', 'starts_with', 'not_equal', 'gte', 'lte', 'gt', 'lt', 'range', 'multi_or'];

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $targetColumns = [];
            $filterColumns = Arr::get($filter, 'columns', []);
            if (!empty($filterColumns) && is_array($filterColumns)) {
                foreach ($filterColumns as $col) {
                    $sanitized = $this->tableManager->sanitizeColumnName($col);
                    if (in_array($sanitized, $existingColumns)) {
                        $targetColumns[] = $sanitized;
                    }
                }
            } elseif (Arr::get($filter, 'column')) {
                $sanitized = $this->tableManager->sanitizeColumnName(Arr::get($filter, 'column'));
                if (in_array($sanitized, $existingColumns)) {
                    $targetColumns[] = $sanitized;
                }
            }

            if (empty($targetColumns)) {
                continue;
            }

            $operator = Arr::get($filter, 'operator', 'like');
            if (!in_array($operator, $allowedOperators, true)) {
                continue;
            }

            $query->where(function ($q) use ($targetColumns, $operator, $filter, $query) {
                foreach ($targetColumns as $colName) {
                    $q->orWhere(function ($inner) use ($colName, $operator, $filter, $query) {
                        $this->applyOperatorCondition($inner, $colName, $operator, $filter, $query);
                    });
                }
            });
        }
    }

    protected function applyOperatorCondition($query, $colName, $operator, $filter, $parentQuery)
    {
        switch ($operator) {
            case 'like':
                global $wpdb;
                $val = Arr::get($filter, 'value', '');
                $query->where($colName, 'LIKE', '%' . $wpdb->esc_like($val) . '%');
                break;

            case 'exact':
                $val = Arr::get($filter, 'value', '');
                $query->where($colName, '=', $val);
                break;

            case 'starts_with':
                global $wpdb;
                $val = Arr::get($filter, 'value', '');
                $query->where($colName, 'LIKE', $wpdb->esc_like($val) . '%');
                break;

            case 'not_equal':
                $val = Arr::get($filter, 'value', '');
                $query->where($colName, '!=', $val);
                break;

            case 'gte':
                $column   = $this->getColumnBySanitizedName($colName);
                $val      = $this->normalizeNumericBound(Arr::get($filter, 'value', 0), $column);
                $castExpr = $parentQuery->raw($this->numericCastExpression($colName, $column));
                $query->where($castExpr, '>=', $val);
                break;

            case 'lte':
                $column   = $this->getColumnBySanitizedName($colName);
                $val      = $this->normalizeNumericBound(Arr::get($filter, 'value', 0), $column);
                $castExpr = $parentQuery->raw($this->numericCastExpression($colName, $column));
                $query->where($castExpr, '<=', $val);
                break;

            // Strict comparisons (used by the sf_match=gt|lt static shortcode filter).
            case 'gt':
                $column   = $this->getColumnBySanitizedName($colName);
                $val      = $this->normalizeNumericBound(Arr::get($filter, 'value', 0), $column);
                $castExpr = $parentQuery->raw($this->numericCastExpression($colName, $column));
                $query->where($castExpr, '>', $val);
                break;

            case 'lt':
                $column   = $this->getColumnBySanitizedName($colName);
                $val      = $this->normalizeNumericBound(Arr::get($filter, 'value', 0), $column);
                $castExpr = $parentQuery->raw($this->numericCastExpression($colName, $column));
                $query->where($castExpr, '<', $val);
                break;

            case 'range':
                $column   = $this->getColumnBySanitizedName($colName);
                $from     = $this->normalizeNumericBound(Arr::get($filter, 'value_from', 0), $column);
                $to       = $this->normalizeNumericBound(Arr::get($filter, 'value_to', 0), $column);
                $castExpr = $parentQuery->raw($this->numericCastExpression($colName, $column));
                $query->where($castExpr, '>=', $from);
                $query->where($castExpr, '<=', $to);
                break;

            case 'multi_or':
                $values      = Arr::get($filter, 'values', []);
                $values      = is_array($values) ? $values : [];
                $values      = array_map('sanitize_text_field', array_slice($values, 0, 100));
                $subOperator = Arr::get($filter, 'sub_operator', 'exact');

                if (empty($values)) {
                    break;
                }

                if ($subOperator === 'like') {
                    global $wpdb;
                    $query->where(function ($q) use ($colName, $values, $wpdb) {
                        foreach ($values as $v) {
                            $q->orWhere($colName, 'LIKE', '%' . $wpdb->esc_like($v) . '%');
                        }
                    });
                } else {
                    $query->whereIn($colName, $values);
                }
                break;

            default:
                break;
        }
    }

    /**
     * Resolve a column definition from its sanitized (DB) column name.
     *
     * @param string $colName
     * @return array|null
     */
    protected function getColumnBySanitizedName($colName)
    {
        foreach ($this->columns as $column) {
            $key = $this->tableManager->sanitizeColumnName(Arr::get($column, 'key', ''));
            if ($key === $colName) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Normalize a numeric filter bound to a float.
     *
     * The client sends canonical dot-decimal bounds (see canonicalNumber in
     * _dtCustomFilters.js). As a defensive fallback for any caller that still
     * passes a European display value, a lone decimal comma is honoured when
     * the column is configured that way before parsing.
     *
     * @param mixed      $value
     * @param array|null $column
     * @return float
     */
protected function normalizeNumericBound($value, $column)
    {
        return NumericFilter::normalizeBound($value, $column);
    }

    /**
     * Build a CAST expression that normalizes the stored cell value before the
     * numeric comparison, honouring the column's decimalSeparator so a stored
     * "1.234,56" casts to 1234.56 rather than 1.234.
     *
     * $colName is already sanitized by sanitizeColumnName(); the REPLACE
     * arguments are static literals, so the expression is safe to embed.
     *
     * @param string     $colName
     * @param array|null $column
     * @return string
     */
protected function numericCastExpression($colName, $column)
    {
        return NumericFilter::castExpression($colName, $column);
    }

    /**
     * Batch insert multiple rows into the dynamic table.
     *
     * @param array $rows Array of associative arrays [key => value]
     * @param int   $startPosition Starting position index
     * @param int   $chunkSize Number of rows per INSERT query
     *
     * A multi-row INSERT fails as a whole when one row is bad (a value MySQL or the
     * table charset rejects), so a failed block is retried row by row: only the rows
     * that really fail are lost, and they are listed in getFailedRows().
     *
     * @return int Number of rows inserted
     */
    public function batchInsert($rows, $startPosition = 0, $chunkSize = 1000)
    {
        $this->failedRows = [];

        if (!$this->tableExists() || empty($rows)) {
            return 0;
        }

        global $wpdb;

        $tableName      = $this->tableManager->getTableName();
        $existingColumns = $this->tableManager->getExistingColumns();
        $systemColumns   = DynamicTableManager::getSystemColumns();
        $ownerId         = get_current_user_id();

        // Build column key mapping: user key => sanitized DB column name
        $columnMap = [];
        foreach ($this->columns as $column) {
            $key          = Arr::get($column, 'key', '');
            $sanitizedKey = $this->tableManager->sanitizeColumnName($key);

            if (in_array($sanitizedKey, $systemColumns)) {
                continue;
            }

            if (in_array($sanitizedKey, $existingColumns)) {
                $columnMap[$key] = $sanitizedKey;
            }
        }

        // Build all prepared row data, keyed by the row's position in $rows
        $preparedRows = [];
        $index        = -1;
        foreach ($rows as $rowValues) {
            $index++;
            $data = [
                DynamicTableManager::COL_POSITION => $startPosition + $index,
                DynamicTableManager::COL_OWNER_ID => $ownerId,
                DynamicTableManager::COL_SETTINGS => null,
            ];

            foreach ($columnMap as $userKey => $dbKey) {
                if (isset($rowValues[$userKey])) {
                    $data[$dbKey] = is_array($rowValues[$userKey])
                        ? json_encode($rowValues[$userKey])
                        : self::repairUtf8($rowValues[$userKey]);
                } else {
                    $data[$dbKey] = null;
                }
            }

            $preparedRows[$index] = $data;
        }

        if (empty($preparedRows)) {
            return 0;
        }

        $inserted = 0;

        // A refused row is recorded in getFailedRows(); printing wpdb's HTML error would
        // corrupt the JSON response of the import that called us (WP_DEBUG_DISPLAY sites).
        $suppressed = $wpdb->suppress_errors(true);

        foreach (array_chunk($preparedRows, $chunkSize, true) as $chunk) {
            $result = $this->insertBlock($tableName, $chunk);
            if ($result !== false) {
                $inserted += $result;
                continue;
            }

            // One bad row fails the whole block: retry row by row so only that row is lost
            foreach ($chunk as $index => $row) {
                $result = count($chunk) === 1 ? false : $this->insertBlock($tableName, [$index => $row]);
                if ($result === false) {
                    $this->failedRows[] = ['index' => $index, 'error' => (string) $wpdb->last_error];
                } else {
                    $inserted += $result;
                }
            }
        }

        $wpdb->suppress_errors($suppressed);

        return $inserted;
    }

    /**
     * Rows the last batchInsert() could not store.
     *
     * @return array [['index' => int, 'error' => string], ...]
     */
    public function getFailedRows()
    {
        return $this->failedRows;
    }

    /**
     * One multi-row INSERT for $rows (prepared column => value arrays).
     *
     * @return int|false Rows inserted, or false when MySQL rejected the statement
     */
    private function insertBlock($tableName, array $rows)
    {
        global $wpdb;

        $columns = array_keys(reset($rows));
        sort($columns);
        $columnList = '`' . implode('`, `', $columns) . '`';

        $placeholders = [];
        $values       = [];

        foreach ($rows as $row) {
            ksort($row);
            $rowPlaceholders = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $rowPlaceholders[] = 'NULL';
                } else {
                    $values[]          = $value;
                    $rowPlaceholders[] = '%s';
                }
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n" . implode(",\n", $placeholders);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->query($wpdb->prepare($sql, $values));
    }

    /**
     * Excel's plain "CSV (Comma delimited)" writes Windows-1252, so a mostly-UTF-8 file can
     * carry a stray byte (0xA3 "£", 0xE9 "é"). MySQL rejects the whole INSERT for one such
     * byte, so convert only the bytes that are not valid UTF-8 and leave the rest as is.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function repairUtf8($value)
    {
        if (!is_string($value) || $value === '' || preg_match('//u', $value)) {
            return $value;
        }

        // Valid UTF-8 sequences pass through; any other single byte is read as Windows-1252
        return preg_replace_callback(
            '/[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|(.)/s',
            function ($match) {
                return isset($match[1]) ? self::windows1252ToUtf8(ord($match[1])) : $match[0];
            },
            $value
        );
    }

    private static function windows1252ToUtf8($byte)
    {
        // 0x80-0x9F differ from Latin-1; the five bytes Windows-1252 leaves undefined become U+FFFD
        static $high = [
            0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020,
            0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
            0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022,
            0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
            0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
        ];

        $code = $byte >= 0xA0 ? $byte : ($high[$byte] ?? 0xFFFD);

        if ($code < 0x800) {
            return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
        }

        return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
    }

    public function duplicate($rowId)
    {
        $row = $this->find($rowId);
        if (!$row) {
            return false;
        }

        $mapped = $this->mapRowToUserKeys($row);

        return $this->insert(
            $mapped['values'],
            $mapped['settings'],
            null,
            get_current_user_id()
        );
    }
}
