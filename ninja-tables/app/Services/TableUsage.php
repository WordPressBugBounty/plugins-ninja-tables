<?php

namespace NinjaTables\App\Services;

/**
 * Where a table is used.
 *
 * The admin could tell you a table existed but never where it was embedded,
 * so deleting one meant searching the site by hand and hoping. This resolves
 * every table's embeds in a single query and caches the result.
 */
class TableUsage
{
    // v2: the cached shape changed when edit_url came out of it. A v1
    // transient holds links resolved against whoever happened to fill it.
    const CACHE_KEY = 'ninja_tables_usage_map_v2';

    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Map of table id => list of ['id', 'title', 'type', 'status', 'edit_url'],
     * holding only the posts the current user is allowed to read.
     *
     * Table access is delegable - `ninja_table_admin_role` filters the roles
     * that reach this admin - so "can see the tables list" is not "can see
     * every private draft on the site that happens to embed one".
     */
    public static function map()
    {
        $map = [];

        foreach (self::candidates() as $tableId => $posts) {
            $allowed = [];

            foreach ($posts as $post) {
                if (!current_user_can('read_post', $post['id'])) {
                    continue;
                }

                // Resolved per request, never cached: get_edit_post_link()
                // answers for the current user and returns null without the
                // capability, so a shared copy would be whoever filled it.
                $post['edit_url'] = get_edit_post_link($post['id'], 'raw');
                $allowed[] = $post;
            }

            if ($allowed) {
                $map[$tableId] = $allowed;
            }
        }

        return $map;
    }

    /**
     * Every embed on the site, before anyone's permissions are considered.
     *
     * This is the expensive half and the same for everyone, so it is what
     * gets cached. One query for the whole site, not one per table.
     */
    protected static function candidates()
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        // Only posts that mention the plugin at all, so the LIKE never scans
        // the full content column for the sake of it.
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result cached via transient (CACHE_KEY)
            "SELECT ID, post_title, post_type, post_status, post_content
               FROM {$wpdb->posts}
              WHERE post_status NOT IN ('auto-draft', 'trash', 'inherit')
                AND post_type NOT IN ('revision', 'ninja-table')
                AND (
                        post_content LIKE '%ninja_tables%'
                     OR post_content LIKE '%ninja_table_builder%'
                     OR post_content LIKE '%ninja-tables/table%'
                )"
        );

        $map = [];

        foreach ((array) $rows as $row) {
            foreach (self::idsIn($row->post_content) as $tableId) {
                if (!isset($map[$tableId])) {
                    $map[$tableId] = [];
                }

                // A post that embeds the same table twice is still one place.
                if (isset($map[$tableId][$row->ID])) {
                    continue;
                }

                $map[$tableId][$row->ID] = [
                    'id'     => intval($row->ID),
                    'title'  => $row->post_title ? $row->post_title : __('(no title)', 'ninja-tables'),
                    'type'   => $row->post_type,
                    'status' => $row->post_status,
                ];
            }
        }

        foreach ($map as $tableId => $posts) {
            $map[$tableId] = array_values($posts);
        }

        set_transient(self::CACHE_KEY, $map, self::CACHE_TTL);

        return $map;
    }

    /**
     * Table ids embedded in a chunk of post content.
     *
     * Covers the two shortcodes and the Gutenberg block, whose attributes are
     * JSON in the block comment.
     */
    public static function idsIn($content)
    {
        if (!$content) {
            return [];
        }

        $ids = [];

        // [ninja_tables id="12"] and [ninja_table_builder id='12']
        if (preg_match_all('/\[ninja_tables?(?:_builder)?[^\]]*?id\s*=\s*["\']?(\d+)/i', $content, $m)) {
            $ids = array_merge($ids, $m[1]);
        }

        // <!-- wp:ninja-tables/table {"tableId":12} -->
        if (preg_match_all('/wp:ninja-tables\/table\s*\{[^}]*?"tableId"\s*:\s*"?(\d+)/i', $content, $m)) {
            $ids = array_merge($ids, $m[1]);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Where one table is used.
     */
    public static function forTable($tableId)
    {
        $map = self::map();

        return isset($map[$tableId]) ? $map[$tableId] : [];
    }

    /**
     * Any post being saved can add or remove an embed, so the map goes.
     */
    public static function flush()
    {
        delete_transient(self::CACHE_KEY);
    }
}
