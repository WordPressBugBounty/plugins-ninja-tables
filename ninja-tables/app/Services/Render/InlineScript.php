<?php

namespace NinjaTables\App\Services\Render;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prints `window[name] = {...}` on a hook. Used to hand per-table config to the frontend bundles.
 */
class InlineScript
{
    public static function window($name, $data, $hook = 'wp_footer', $priority = 10)
    {
        $print = function () use ($name, $data) {
            ?>
            <script type="text/javascript">
                window['<?php echo esc_js($name); ?>'] = <?php echo wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            </script>
            <?php
        };

        if (static::printNow()) {
            $print();
            return;
        }

        add_action($hook, $print, $priority);
    }

    /**
     * Elementor's editor renders widget HTML in admin requests (the editor page itself and
     * its elementor_ajax calls) that never fire wp_footer, so table config/CSS must be
     * printed right next to the table markup there.
     *
     * Limited to the table render actions, which run inside the shortcode's output buffer, so the
     * markup always lands in the returned table HTML and never leaks into an AJAX/JSON response.
     */
    public static function printNow()
    {
        if (!doing_action('ninja_tables-render-table-footable') && !doing_action('ninja_tables-render-table-datatables')) {
            return false;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check

        return is_admin() && in_array($action, ['elementor', 'elementor_ajax'], true);
    }
}
