<?php

namespace NinjaTables\App\Hooks\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaTables\Framework\Support\Arr;

/**
 * Backward-compatibility shims for older Ninja Tables Pro builds.
 */
class CompatHandler
{
    public function register()
    {
        // Pro < 5.0.0 still references the pre-framework ArrayHelper class.
        if (defined('NINJAPROPLUGIN_VERSION') && version_compare(NINJAPROPLUGIN_VERSION, '5.0.0', '<')) {
            add_action('init', [$this, 'aliasLegacyArrayHelper']);
        }

        // Older Pro builds the WP Posts query without a post status. Public feeds run in
        // admin-ajax (is_admin()), where WP_Query then returns drafts/pending/private posts.
        // Early priority so a site's own args filter can still choose statuses.
        add_filter('ninja_table_post_table_args', [$this, 'defaultPostTableStatus'], 5);
    }

    public function defaultPostTableStatus($args)
    {
        if (is_array($args) && empty($args['post_status'])) {
            $args['post_status'] = 'publish';
        }

        return $args;
    }

    public function aliasLegacyArrayHelper()
    {
        if (!class_exists('NinjaTables\\Classes\\ArrayHelper', false)) {
            class_alias(Arr::class, 'NinjaTables\\Classes\\ArrayHelper');
        }
    }
}
