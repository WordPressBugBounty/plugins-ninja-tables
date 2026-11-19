<?php

namespace NinjaTables\App\Support;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\Framework\Support\Arr;

/**
 * Server-side ordering for date columns.
 *
 * Date cells are stored as the formatted text the user sees, so sorting the
 * raw value is alphabetical. This parses each value with the column's
 * Moment.js format (via STR_TO_DATE) so rows order by time instead.
 */
class DateSort
{
    // Moment.js tokens and their STR_TO_DATE specifiers, longest first per letter
    const TOKENS = [
        'YYYY' => '%Y', 'YY' => '%y',
        'MMMM' => '%M', 'MMM' => '%b', 'MM' => '%m', 'M' => '%c',
        'Do'   => '%D', 'DD' => '%d', 'D' => '%e',
        'dddd' => '%W', 'ddd' => '%a',
        'HH'   => '%H', 'H' => '%k', 'hh' => '%h', 'h' => '%l',
        'mm'   => '%i', 'm' => '%i', 'ss' => '%s', 's' => '%s',
        'A'    => '%p', 'a' => '%p',
    ];

    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Convert a Moment.js format to a MySQL STR_TO_DATE format.
     *
     * @param string $momentFormat
     * @return string|null null when the format uses a token MySQL can't parse
     */
    public static function toMysql($momentFormat)
    {
        $momentFormat = (string) $momentFormat;
        if (trim($momentFormat) === '') {
            return null;
        }

        $tokens  = implode('|', array_map('preg_quote', array_keys(self::TOKENS)));
        $pattern = '/\[[^\]]*\]|' . $tokens . '|[A-Za-z%]|./us';

        if (!preg_match_all($pattern, $momentFormat, $matches)) {
            return null;
        }

        $mysql = '';
        foreach ($matches[0] as $part) {
            if (isset(self::TOKENS[$part])) {
                $mysql .= self::TOKENS[$part];
            } elseif ($part[0] === '[' && substr($part, -1) === ']') {
                // [escaped text] is literal; a '%' inside it would read as a specifier
                $literal = substr($part, 1, -1);
                if (strpos($literal, '%') !== false) {
                    return null;
                }
                $mysql .= $literal;
            } elseif (preg_match('/^[A-Za-z%]$/', $part)) {
                return null;
            } else {
                $mysql .= $part;
            }
        }

        return $mysql;
    }

    /**
     * STR_TO_DATE formats to try for a column, most specific first.
     *
     * @param array|null $column column config (dateFormat, showTime, timeFormat)
     * @return string[] empty when the format can't be converted
     */
    public static function formatsFor($column)
    {
        $dateFormat = trim((string) Arr::get((array) $column, 'dateFormat', ''));

        // No format set: the table displays MM/DD/YYYY, older SQL sorts assumed YYYY-MM-DD
        $dateFormats = $dateFormat === '' ? ['%m/%d/%Y', '%Y-%m-%d'] : [self::toMysql($dateFormat)];
        if (in_array(null, $dateFormats, true)) {
            return [];
        }

        $timeFormat = null;
        if (Arr::get((array) $column, 'showTime') === 'yes' && Arr::get((array) $column, 'timeFormat')) {
            $timeFormat = self::toMysql(Arr::get((array) $column, 'timeFormat'));
            if ($timeFormat === null) {
                return [];
            }
        }

        $formats = [];
        foreach ($dateFormats as $format) {
            if ($timeFormat !== null) {
                $formats[] = $format . ' ' . $timeFormat;
            }
            // Rows saved before time was switched on have the date only
            $formats[] = $format;
        }

        return $formats;
    }

    /**
     * Order a query by a date column.
     *
     * @param object $query         query builder
     * @param string $valueSql      SQL for the cell value, e.g. a backticked column
     * @param array  $valueBindings bindings used by $valueSql
     * @param string $direction     ASC or DESC
     * @param array  $column        column config
     * @return bool false when the format can't be parsed; the caller keeps its text sort
     */
    public static function orderBy($query, $valueSql, array $valueBindings, $direction, $column)
    {
        $formats = self::formatsFor($column);
        if (!$formats) {
            return false;
        }

        $direction = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';

        list($translatedSql, $nameBindings) = self::translateNames($valueSql, Arr::get((array) $column, 'dateFormat', ''));

        $parts    = [];
        $bindings = [];
        foreach ($formats as $format) {
            $parts[]  = "STR_TO_DATE({$translatedSql}, ?)";
            $bindings = array_merge($bindings, $valueBindings, $nameBindings, [$format]);
        }

        $expression = count($parts) > 1 ? 'COALESCE(' . implode(', ', $parts) . ')' : $parts[0];

        $query->orderByRaw("{$expression} {$direction}", $bindings);
        // Values that don't parse all sort as NULL; keep those in text order
        $query->orderByRaw("{$valueSql} {$direction}", $valueBindings);

        return true;
    }

    /**
     * Wrap the value in REPLACE() calls that turn the site locale's month and
     * weekday names into English, which is all STR_TO_DATE understands.
     *
     * @param string $valueSql
     * @param string $dateFormat Moment.js format
     * @return array [sql, bindings]; bindings follow the value's own
     */
    protected static function translateNames($valueSql, $dateFormat)
    {
        global $wp_locale;

        $bindings = [];
        if (!$wp_locale) {
            return [$valueSql, $bindings];
        }

        $pairs = [];

        if (strpos($dateFormat, 'MMM') !== false) {
            $full = strpos($dateFormat, 'MMMM') !== false;
            foreach (self::MONTHS as $index => $english) {
                $local   = $wp_locale->get_month($index + 1);
                $pairs[] = $full
                    ? [$local, $english]
                    : [$wp_locale->get_month_abbrev($local), substr($english, 0, 3)];
            }
        }

        if (strpos($dateFormat, 'ddd') !== false) {
            $full = strpos($dateFormat, 'dddd') !== false;
            foreach (self::WEEKDAYS as $index => $english) {
                $local   = $wp_locale->get_weekday($index);
                $pairs[] = $full
                    ? [$local, $english]
                    : [$wp_locale->get_weekday_abbrev($local), substr($english, 0, 3)];
            }
        }

        foreach ($pairs as $pair) {
            list($local, $english) = $pair;
            if ($local && strcasecmp($local, $english) !== 0) {
                $valueSql   = "REPLACE({$valueSql}, ?, ?)";
                $bindings[] = $local;
                $bindings[] = $english;
            }
        }

        return [$valueSql, $bindings];
    }
}
