<?php

namespace NinjaTables\App\Modules\Lead;

use NinjaTables\App\Models\NinjaTableItem;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Weekly, opt-in usage counters. No personal data and nothing from inside a table.
 *
 * Contract: fluent-license-cf-proxy docs/plugin-usage-stats-integration.md. Metrics are one flat
 * list (snake_case keys, numbers, booleans, short strings or short string lists). The optional
 * "maintenance" block describes the site (active plugins, theme, title, language) and rides along
 * only when the admin ticked its own box.
 */
class UsageStats
{
    const ENDPOINT = 'https://fluentapi.wpmanageninja.com/stats';

    // ['usage' => 'yes'|'no', 'plugins' => 'yes'|'no', 'updated_at' => int]
    const CONSENT_OPTION = '_ninja_tables_usage_sharing';

    const LAST_SENT_OPTION = '_ninja_tables_usage_stats_last_sent';

    // Shared by the Fluent plugins on purpose: one of them sends the site block each week
    const MAINTENANCE_OPTION = '_fluent_last_m_run';

    const SEND_NOW_HOOK = 'ninja_tables_send_usage_stats_now';

    /**
     * Sites that clicked "Opt-in" in the old pop-up (before 5.5.0) agreed to share their site and
     * plugins data. The product owner decided that consent carries over to the weekly usage
     * report, once: they are not asked again. The email is not carried over (that consent was
     * for update notices, not this list), so the site block's admin_email stays empty for them.
     */
    public static function migrateOldOptin()
    {
        if (get_option(self::CONSENT_OPTION) !== false || self::oldAnswer() !== 'yes') {
            return false;
        }

        update_option(self::CONSENT_OPTION, [
            'usage'      => 'yes',
            'plugins'    => 'yes',
            'updated_at' => time(),
            'from'       => 'lead_optin', // carried over from the old pop-up
        ], false);

        if (!wp_next_scheduled(self::SEND_NOW_HOOK)) {
            wp_schedule_single_event(time() + 60, self::SEND_NOW_HOOK);
        }

        return true;
    }

    /** 'yes', 'no' or '' — the answer to the old pop-up (_ninja_table_lead_options) */
    private static function oldAnswer()
    {
        $old = get_option('_ninja_table_lead_options', []);

        return is_array($old) && isset($old['lead_optin_status']) && in_array($old['lead_optin_status'], ['yes', 'no'], true)
            ? $old['lead_optin_status'] : '';
    }

    public static function consent()
    {
        $consent = get_option(self::CONSENT_OPTION, []);
        $consent = is_array($consent) ? $consent : [];

        return [
            'usage'   => (isset($consent['usage']) && $consent['usage'] === 'yes') ? 'yes' : 'no',
            'plugins' => (isset($consent['plugins']) && $consent['plugins'] === 'yes') ? 'yes' : 'no',
            // false until the admin saved a choice: the card then shows the box ticked.
            // A "no" to the old pop-up counts as a choice, so it starts unticked for them.
            'decided' => isset($consent['usage']) || self::oldAnswer() === 'no',
        ];
    }

    /**
     * Save both usage choices. Site details need usage sharing on; turning usage off turns both off.
     */
    public static function saveConsent($usage, $plugins)
    {
        $before  = self::consent();
        $usage   = $usage === 'yes' ? 'yes' : 'no';
        $plugins = ($usage === 'yes' && $plugins === 'yes') ? 'yes' : 'no';

        update_option(self::CONSENT_OPTION, [
            'usage'      => $usage,
            'plugins'    => $plugins,
            'updated_at' => time(),
        ], false);

        // First report right after opting in, from a cron event: the admin's click never waits on our API
        if ($usage === 'yes' && $before['usage'] !== 'yes' && !wp_next_scheduled(self::SEND_NOW_HOOK)) {
            wp_schedule_single_event(time() + 60, self::SEND_NOW_HOOK);
        }

        return self::consent();
    }

    public static function isAllowed()
    {
        /**
         * Turn usage stats off for a site, whatever the admin chose.
         *
         * @param bool $allowed
         */
        return (bool) apply_filters('ninja_tables/allow_usage_stats', self::consent()['usage'] === 'yes');
    }

    public static function maybeSend($force = false)
    {
        if (!self::isAllowed()) {
            return false;
        }

        $lastSent = (int) get_option(self::LAST_SENT_OPTION, 0);

        // 6 days, not 7: WP-Cron drifts, and the Worker keeps one row per week anyway
        if (!$force && $lastSent && (time() - $lastSent) < 6 * DAY_IN_SECONDS) {
            return false;
        }

        $body = [
            'product'         => 'ninja-tables',
            'product_version' => NINJA_TABLES_VERSION,
            'site_url'        => home_url(),
            'metrics'         => self::getMetrics(),
        ];

        $maintenance = self::maintenanceBlock();
        if ($maintenance) {
            $body['maintenance'] = $maintenance;
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'cookies' => [],
        ]);

        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return false;
        }

        // The answer is always 202 and is not retried; only the cadence matters here
        update_option(self::LAST_SENT_OPTION, time(), false);
        if ($maintenance) {
            update_option(self::MAINTENANCE_OPTION, time(), false);
        }

        return true;
    }

    /**
     * Counts only. A collector that fails is left out instead of failing the report.
     */
    public static function getMetrics()
    {
        $collectors = [
            // Left out without Pro, so the panel's Pro-version breakdown counts real versions only
            'pro_version'         => function () {
                return defined('NINJAPROPLUGIN_VERSION') ? NINJAPROPLUGIN_VERSION : null;
            },
            'tables_counts'       => [self::class, 'tableCounts'],
            'rows_counts'         => [self::class, 'rowCounts'],
            'columns_max'         => [self::class, 'columnsMax'],
            'features'            => [self::class, 'features'],
            'woocommerce_active'  => function () { return defined('WC_VERSION'); },
            'fluent_cart_active'  => function () { return defined('FLUENTCART_VERSION'); },
            'fluent_forms_active' => function () { return defined('FLUENTFORM'); },
            'ninja_charts_active' => function () { return defined('NINJA_CHARTS_VERSION'); },
        ];

        /**
         * Add-ons (e.g. Ninja Tables Pro) can append counters.
         * A collector returns one value, or an array of several flat metrics.
         * Keys: snake_case. Values: int|float|bool|short string|list of short strings.
         */
        $collectors = apply_filters('ninja_tables/usage_stats_collectors', $collectors);

        $metrics = [];
        foreach ($collectors as $key => $collector) {
            try {
                $value = is_callable($collector) ? call_user_func($collector) : $collector;
            } catch (\Throwable $e) {
                continue; // never break the weekly task over a statistic
            }

            // Grouped collectors return several metrics at once (the endpoint takes no nesting)
            if (is_array($value) && $value && array_keys($value) !== range(0, count($value) - 1)) {
                $metrics = array_merge($metrics, $value);
            } elseif ($value !== null) {
                $metrics[$key] = $value;
            }
        }

        return $metrics;
    }

    /** Published table ids by data source (cached for one report) */
    private static function tablesBySource()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $ids = get_posts([
            'post_type'      => 'ninja-table',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);
        update_meta_cache('post', $ids); // one query for every get_post_meta() below

        $map = [];
        foreach ($ids as $id) {
            $src            = get_post_meta($id, '_ninja_tables_data_provider', true);
            $map[(int) $id] = $src ? $src : 'default';
        }

        return $map;
    }

    public static function tableCounts()
    {
        $keys = [
            'default'        => 'tables_src_default',
            'drag_and_drop'  => 'tables_src_drag_and_drop',
            'csv'            => 'tables_src_csv',
            'external-csv'   => 'tables_src_csv',
            'google-csv'     => 'tables_src_google_sheets',
            'google'         => 'tables_src_google_sheets',
            'wp-posts'       => 'tables_src_wp_posts',
            'wp_woo'         => 'tables_src_woo',
            'wp_woo_reviews' => 'tables_src_woo_reviews',
            'fluent-form'    => 'tables_src_fluent_form',
            'raw_sql'        => 'tables_src_raw_sql',
            'wp_fct'         => 'tables_src_fluent_cart',
        ];

        // Each group adds up to "tables": Drag & Drop tables have no engine (tables_engine_none)
        $metrics = ['tables' => 0, 'tables_src_other' => 0, 'tables_engine_footable' => 0, 'tables_engine_datatables' => 0, 'tables_engine_none' => 0];
        foreach (array_unique($keys) as $key) {
            $metrics[$key] = 0;
        }

        foreach (self::tablesBySource() as $id => $src) {
            $metrics['tables']++;
            $metrics[isset($keys[$src]) ? $keys[$src] : 'tables_src_other']++;

            if ($src === 'drag_and_drop') {
                $metrics['tables_engine_none']++;
                continue;
            }
            $settings = get_post_meta($id, '_ninja_table_settings', true);
            $library  = is_array($settings) && !empty($settings['library']) ? $settings['library'] : 'footable';
            $metrics[$library === 'datatables' ? 'tables_engine_datatables' : 'tables_engine_footable']++;
        }

        return $metrics;
    }

    /**
     * Row counts of tables that store their rows in Ninja Tables (Default source). The buckets
     * plus tables_rows_not_stored add up to "tables": the other sources (WooCommerce, Sheets,
     * Drag & Drop, ...) keep their rows elsewhere, so they are counted there instead.
     */
    public static function rowCounts()
    {
        $sources    = self::tablesBySource();
        $defaultIds = array_keys(array_filter($sources, function ($src) {
            return $src === 'default';
        }));

        $counts = array_fill_keys($defaultIds, 0);
        if ($defaultIds) {
            $rows = NinjaTableItem::selectRaw('table_id, COUNT(*) AS n')
                ->whereIn('table_id', $defaultIds)
                ->groupBy('table_id')
                ->get();
            foreach ($rows as $row) {
                $counts[(int) $row->table_id] = (int) $row->n;
            }
        }

        $metrics = [
            'rows_total'         => array_sum($counts),
            'rows_max'           => $counts ? max($counts) : 0,
            'tables_rows_lt_100' => 0,
            'tables_rows_100_1k' => 0,
            'tables_rows_1k_10k' => 0,
            'tables_rows_gt_10k' => 0,
            'tables_rows_not_stored' => count($sources) - count($defaultIds),
        ];
        foreach ($counts as $n) {
            if ($n < 100) {
                $metrics['tables_rows_lt_100']++;
            } elseif ($n < 1000) {
                $metrics['tables_rows_100_1k']++;
            } elseif ($n <= 10000) {
                $metrics['tables_rows_1k_10k']++;
            } else {
                $metrics['tables_rows_gt_10k']++;
            }
        }

        return $metrics;
    }

    public static function columnsMax()
    {
        $max = 0;
        foreach (array_keys(self::tablesBySource()) as $id) {
            $columns = get_post_meta($id, '_ninja_table_columns', true);
            if (is_array($columns)) {
                $max = max($max, count($columns));
            }
        }

        return $max;
    }

    /** Which features any published table uses (names only) */
    public static function features()
    {
        $features = [];
        foreach (array_keys(self::tablesBySource()) as $id) {
            if (get_post_meta($id, '_ninja_table_custom_filters', true)) {
                $features['custom_filters'] = true;
            }
            if (trim((string) get_post_meta($id, '_ninja_tables_custom_css', true)) !== '') {
                $features['custom_css'] = true;
            }
            if (trim((string) get_post_meta($id, '_ninja_tables_custom_js', true)) !== '') {
                $features['custom_js'] = true;
            }
            $edit = get_post_meta($id, '_ninja_table_frontedit_settings', true);
            if (is_array($edit) && isset($edit['allow_frontend']) && $edit['allow_frontend'] === 'yes') {
                $features['frontend_editing'] = true;
            }
        }

        return array_keys($features);
    }

    /**
     * The site block (/plugin-maintenance shape). Only with its own consent, at most once in 6 days
     * across the Fluent plugins, and admin_email only when the admin opted in to emails.
     */
    private static function maintenanceBlock()
    {
        if (self::consent()['plugins'] !== 'yes') {
            return null;
        }

        $last = (int) get_option(self::MAINTENANCE_OPTION, 0);
        if ($last && (time() - $last) < 6 * DAY_IN_SECONDS) {
            return null;
        }

        global $wp_version;
        $optin = SubscriberOptin::get();

        return [
            'plugin_version' => NINJA_TABLES_VERSION,
            'php_version'    => PHP_VERSION,
            'wp_version'     => $wp_version,
            'plugins'        => array_values((array) get_option('active_plugins', [])),
            'site_lang'      => get_bloginfo('language'),
            'theme'          => wp_get_theme()->get('Name'),
            'site_title'     => get_bloginfo('name'),
            'site_url'       => home_url(),
            'admin_email'    => !empty($optin['email']) ? get_bloginfo('admin_email') : '',
        ];
    }
}
