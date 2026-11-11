<?php

namespace NinjaTables\App\Models;

use NinjaTables\App\Models\Casts\CustomDateCast;
use NinjaTables\App\Support\Sanitize;
use NinjaTables\Framework\Support\Sanitizer;

class NinjaTableItem extends Model
{
    // Most rows read from a connected source for one admin search
    const SEARCH_ROW_LIMIT = 10000;

    protected $primaryKey = 'id';
    protected $table = 'ninja_table_items';
    protected $casts = [
        'created_at' => CustomDateCast::class,
    ];

    /**
     * @param array $sort Optional admin header sort: ['by' => column key, 'dir' => 'asc'|'desc'].
     *                    Sorts every row, not just one page. Ignored unless the key is one of
     *                    the table's columns. Callers that pass six arguments (Pro) are unchanged.
     */
    protected function getItems($tableId, $perPage, $currentPage, $skip, $search, $dataSourceType, $sort = [])
    {
        if ($dataSourceType == 'default') {
            list($orderByField, $orderByType) = $this->getTableSortingParams($tableId);

            $query = $this->where('table_id', $tableId);

            if ($search) {
                $query->where('value', 'LIKE', "%$search%");
            }
            $total = $query->count();
            $query->take($perPage)
                  ->skip($skip)
                  ->limit($perPage);

            if (!$this->applyColumnSort($query, $tableId, $sort)) {
                $query->orderBy($orderByField, $orderByType);
            }

            $data = $query->get();

            $response = array();

            $hasSettings = true;
            foreach ($data as $item) {
                $item        = (object)$item->toArray();
                $hasSettings = property_exists($item, 'settings');
                $settings    = (object)array();
                if ($hasSettings) {
                    $settings = Sanitize::rowSettings($item->settings);
                    if ( ! is_array($settings)) {
                        $settings = (object)array();
                    }
                }
                $createdBy = '';
                if (property_exists($item, 'owner_id')) {
                    $userInfo = get_userdata($item->owner_id);
                    if ($userInfo && property_exists($userInfo->data, 'display_name')) {
                        $createdBy = $userInfo->data->display_name;
                    }
                }

                $response[] = array(
                    'id'         => $item->id,
                    'created_at' => $item->created_at,
                    'settings'   => $settings,
                    'created_by' => $createdBy,
                    'position'   => property_exists($item, 'position') ? $item->position : null,
                    'values'     => json_decode($item->value, true)
                );
            }

            if ( ! $hasSettings) {
                // We have to migrate the data now
            }
        } elseif (trim((string)$search) !== '') {
            list($response, $total) = $this->searchProviderRows($tableId, $perPage, $skip, $search, $dataSourceType);
        } else {
            list($response, $total) = apply_filters(
                'ninja_tables_get_table_data_' . $dataSourceType,
                array(array(), 0),
                $tableId,
                $perPage,
                $skip
            );
        }

        // Needed for other data source providers
        list($response, $total) = apply_filters(
            'ninja_tables_get_table_data',
            array($response, $total),
            $tableId,
            $perPage,
            $skip
        );

        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'last_page'    => ceil($total / $perPage),
            'data'         => $response,
            'data_source'  => $dataSourceType
        ];
    }

    /**
     * Search a connected source. Providers only hand back rows, so ask for them
     * from the start, keep the matches, then cut the requested page out of those.
     * The limit is a large page size rather than -1: several providers pass the
     * page size to array_slice(), where -1 would drop the last row.
     */
    protected function searchProviderRows($tableId, $perPage, $skip, $search, $dataSourceType)
    {
        // Fluent Forms takes its page from the request, not from the offset
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $hadPage      = array_key_exists('page', $_GET);
        $previousPage = $hadPage ? $_GET['page'] : null;
        $_GET['page'] = 1;

        $providerData = apply_filters(
            'ninja_tables_get_table_data_' . $dataSourceType,
            array(array(), 0),
            $tableId,
            self::SEARCH_ROW_LIMIT,
            0
        );

        if ($hadPage) {
            $_GET['page'] = $previousPage;
        } else {
            unset($_GET['page']);
        }
        // phpcs:enable

        $matches = self::filterRowsBySearch($providerData[0], $search);

        return array(array_slice($matches, $skip, $perPage), count($matches));
    }

    /**
     * Keep the rows with a cell containing the search term, ignoring case and
     * looking only at the text a cell shows, not its HTML tags and attributes.
     */
    public static function filterRowsBySearch($rows, $search)
    {
        $search = trim((string)$search);
        if ($search === '') {
            return (array)$rows;
        }

        return array_values(array_filter((array)$rows, function ($row) use ($search) {
            $row = (array)$row;

            return isset($row['values']) && self::cellContains($row['values'], $search);
        }));
    }

    protected static function cellContains($cell, $search)
    {
        if (is_array($cell) || is_object($cell)) {
            foreach ((array)$cell as $value) {
                if (self::cellContains($value, $search)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_scalar($cell)) {
            return false;
        }

        $text = html_entity_decode(wp_strip_all_tags((string)$cell), ENT_QUOTES, 'UTF-8');

        return function_exists('mb_stripos')
            ? mb_stripos($text, $search, 0, 'UTF-8') !== false
            : stripos($text, $search) !== false;
    }

    /**
     * Order a default table's rows by one column's value inside the JSON row, the same way
     * DefaultProvider::orderByJsonQuery() does for the live table. Returns false (leave the
     * saved order) when the column is not one of the table's own.
     */
    protected function applyColumnSort($query, $tableId, $sort)
    {
        $sortBy = is_array($sort) && isset($sort['by']) ? (string)$sort['by'] : '';
        if ($sortBy === '') {
            return false;
        }

        $column = null;
        foreach ((array)ninja_table_get_table_columns($tableId, 'admin') as $tableColumn) {
            if (isset($tableColumn['key']) && $tableColumn['key'] === $sortBy) {
                $column = $tableColumn;
                break;
            }
        }
        if (!$column) {
            return false;
        }

        $direction = (isset($sort['dir']) && strtolower($sort['dir']) === 'desc') ? 'DESC' : 'ASC';
        // The JSON path is a bound value (the key is already one of the
        // table's own columns); quoting the key keeps keys like first-name valid
        $path  = '$."' . addcslashes($column['key'], '"\\') . '"';
        $value = 'JSON_UNQUOTE(JSON_EXTRACT(value, ?))';
        $type  = isset($column['data_type']) ? $column['data_type'] : '';

        if ($type === 'number') {
            $query->orderByRaw("CAST($value AS DECIMAL(30,10)) $direction", [$path]);
        } elseif ($type === 'date') {
            $format = self::mysqlDateFormat(isset($column['dateFormat']) ? $column['dateFormat'] : '');
            $query->orderByRaw("STR_TO_DATE($value, ?) $direction", [$path, $format]);
        } else {
            // Number-like text by value first (999 before 1000), then text
            // order (the table collation ignores case)
            $query->orderByRaw("CAST($value AS DECIMAL(30,10)) $direction", [$path])
                  ->orderByRaw("$value $direction", [$path]);
        }
        $query->orderBy('id', $direction);

        return true;
    }

    // Same mapping as DefaultProvider::convertDateFormatToMysql() (protected there)
    protected static function mysqlDateFormat($dateFormat)
    {
        if (!$dateFormat) {
            return '%Y-%m-%d';
        }

        return strtr($dateFormat, [
            'YYYY' => '%Y', 'YY' => '%y', 'MM' => '%m', 'M' => '%c', 'DD' => '%d', 'D' => '%e',
            'HH' => '%H', 'hh' => '%h', 'mm' => '%i', 'ss' => '%s',
        ]);
    }

    /*
     * Get the order by field and order by type values.
     *
     * @param        $tableId
     * @param null $tableSettings
     *
     * @return array
     */
    public function getTableSortingParams($tableId, $tableSettings = null)
    {
        $tableSettings = $tableSettings ?: ninja_table_get_table_settings($tableId, 'admin');

        $orderByField = 'created_at';
        $orderByType  = 'DESC';

        if (isset($tableSettings['sorting_type'])) {
            if ($tableSettings['sorting_type'] === 'manual_sort') {
                $orderByField = 'position';
                $orderByType  = 'ASC';
            } elseif ($tableSettings['sorting_type'] === 'by_created_at') {
                $orderByField = 'created_at';
                if ($tableSettings['default_sorting'] === 'new_first') {
                    $orderByType = 'DESC';
                } else {
                    $orderByType = 'ASC';
                }
            }
        }

        return [$orderByField, $orderByType];
    }

    protected function deleteTableItem($tableId, $ids)
    {
        do_action('ninja_table_before_items_deleted', $ids, $tableId);
        $this->where('table_id', $tableId)->whereIn('id', $ids)->delete();
        do_action('ninja_table_after_items_deleted', $ids, $tableId);

        ninjaTablesClearTableDataCache($tableId);
    }

    /**
     * Serialize date attributes without converting the timezone.
     *
     * The ORM treats created_at/updated_at as timestamps and serializes them
     * through asDateTime(), which reads the naive DB value as site local time
     * and then converts it to UTC. These columns are already written as plain
     * strings, so that conversion shifted every date by the site's GMT offset.
     * Formatting the value as-is keeps what was stored.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Normalize a user supplied create date to a MySQL DATETIME string.
     *
     * MySQL silently stores anything it cannot parse as 0000-00-00 00:00:00 on
     * servers without STRICT_TRANS_TABLES, which then reads back as
     * -0001-11-30. Values that cannot be parsed are dropped instead, so the
     * existing created_at is left untouched.
     *
     * @param string $value
     * @return string Empty string when the value cannot be parsed.
     */
    protected function normalizeCreatedAt($value)
    {
        if (!is_string($value) || !$value) {
            return '';
        }

        $value = trim($value);

        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($date && $date->format('Y-m-d H:i:s') === $value) {
            return $value;
        }

        // Otherwise accept anything strtotime() understands, e.g. ISO 8601.
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        $normalized = gmdate('Y-m-d H:i:s', $timestamp);

        // strtotime() maps 0000-00-00 onto a negative timestamp rather than
        // failing, so make sure the result is a real four digit year.
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized)) {
            return '';
        }

        return $normalized;
    }

    protected function insertTableItem($id, $tableId, $formattedRow, $created_at, $insertAfterId, $settings)
    {
        $attributes = array(
            'table_id'   => $tableId,
            'attribute'  => 'value',
            'value'      => $this->asJson($formattedRow),
            'owner_id'   => get_current_user_id(),
            'updated_at' => gmdate('Y-m-d H:i:s')
        );


        if ($settings && $settings !== 'null') {
            $attributes['settings'] = maybe_serialize(
                wp_unslash(
                    ninja_tables_sanitize_array($settings)
                )
            );
        }


        $createdAt = '';
        if ($created_at !== null) {
            $createdAt = $this->normalizeCreatedAt(
                Sanitizer::sanitizeTextField($created_at)
            );
        }
        if ($createdAt) {
            $attributes['created_at'] = $createdAt;
        }


        if ($id = intval($id)) {
            $existing = $this->where('id', $id)->where('table_id', $tableId)->first();
            if (!$existing) {
                return null;
            }
            do_action('ninja_table_before_update_item', $id, $tableId, $attributes);
            $this->where('id', $id)->where('table_id', $tableId)->update($attributes);
            do_action('ninja_table_after_update_item', $id, $tableId, $attributes);
        } else {
            if ($insertAfterId !== null) {
                list($orderByField, $orderByType) = $this->getTableSortingParams($tableId);
                if ($orderByField == 'created_at') {
                    // Calculate the insert position Date
                    $prevItemId   = absint($insertAfterId);
                    $previousItem = $this->where('id', $prevItemId)->first();
                    if ($previousItem) {
                        if ($orderByType == 'ASC') {
                            // ASC means, We have to minus time to created_at
                            $newDateStamp = strtotime($previousItem->created_at) + 1;
                        } else {
                            $newDateStamp = strtotime($previousItem->created_at) - 1;
                        }
                        $attributes['created_at'] = gmdate('Y-m-d H:i:s', $newDateStamp);
                        $this->fixCreatedAtDate($tableId, $previousItem->created_at, $orderByType);
                    }
                }
            }

            if ( ! isset($attributes['created_at'])) {
                $attributes['created_at'] = gmdate('Y-m-d H:i:s');
            }

            $attributes = apply_filters('ninja_tables_item_attributes', $attributes);

            do_action('ninja_table_before_add_item', $tableId, $attributes);
            $id = $insertId = $this->insertGetId($attributes);
            do_action('ninja_table_after_add_item', $insertId, $tableId, $attributes);
        }

        $item = $this->where('id', $id)->where('table_id', $tableId)->first();

        if (!$item) {
            return null;
        }

        ninjaTablesClearTableDataCache($tableId);

        update_post_meta($tableId, '_last_edited_by', get_current_user_id());
        update_post_meta($tableId, '_last_edited_time', gmdate('Y-m-d H:i:s'));

        $itemSettings = '';

        if ($item && $item->settings !== null) {
            $itemSettings = Sanitize::rowSettings($item->settings);
        }

        if ( ! is_array($itemSettings)) {
            $itemSettings = (object)array();
        }

        return [
            'id'         => $item->id,
            'values'     => $formattedRow,
            'row'        => json_decode($item->value),
            'created_at' => $item->created_at,
            'settings'   => $itemSettings,
            'position'   => isset($item->position) ? $item->position : null
        ];
    }

    private function fixCreatedAtDate($tableId, $refDate, $orderType)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . esc_sql(ninja_tables_db_table_name());
        if ($orderType == 'ASC') {
            $query = "UPDATE {$tableName}
                  SET created_at = ADDTIME(created_at, 2)
                  WHERE table_id = %d
                  AND created_at > %s";
        } else {
            $orderType = 'DESC';
            $query     = "UPDATE {$tableName}
                  SET created_at = SUBTIME(created_at, 2)
                  WHERE table_id = %d
                  AND created_at < %s";
        }

        $bindings = [
            $tableId,
            $refDate
        ];
        $query    .= " ORDER BY created_at " . $orderType;
        $wpdb->query($wpdb->prepare($query, $bindings)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    protected function editSingleCell($rowId, $row, $columnKey, $columnValue)
    {
        $values             = json_decode($row->value, true);
        $values[$columnKey] = $columnValue;

        $this->where('id', $rowId)->update([
            'value'      => $this->asJson($values),
            'updated_at' => gmdate('Y-m-d H:i:s')
        ]);

        ninjaTablesClearTableDataCache($row->table_id);
        update_post_meta($row->table_id, '_last_edited_by', get_current_user_id());
        update_post_meta($row->table_id, '_last_edited_time', gmdate('Y-m-d H:i:s'));
    }

    protected function selectedRows($tableId)
    {
        return $this->select(array(
            'position',
            'owner_id',
            'attribute',
            'value',
            'settings',
            'created_at',
            'updated_at'
        ))
             ->where('table_id', $tableId)
             ->get();
    }
}
