<?php

namespace NinjaTables\App\Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The single seam between free and Ninja Tables Pro. Grep for Pro:: to find every Pro-gated branch.
 */
class Pro
{
    public static function active()
    {
        return defined('NINJATABLESPRO');
    }

    public static function sortable()
    {
        return defined('NINJATABLESPRO_SORTABLE');
    }

    public static function version()
    {
        return defined('NINJAPROPLUGIN_VERSION') ? NINJAPROPLUGIN_VERSION : null;
    }

    public static function url()
    {
        return defined('NINJAPROPLUGIN_URL') ? NINJAPROPLUGIN_URL : null;
    }

    /**
     * The Ninja Tables Pro purchase page, tagged for one placement.
     * Same parameters as resources/admin/utils/proUrl.js.
     *
     * @param string $content  utm_content: the exact placement, e.g. 'plugin_row_meta'
     * @param string $campaign utm_campaign: upgrade_pro, license_renew, license_upgrade or xsell_<product>
     */
    public static function upgradeUrl($content, $campaign = 'upgrade_pro')
    {
        return static::utmUrl('https://ninjatables.com/discount-deal/', $content, $campaign);
    }

    /**
     * Add the shared WPManageNinja UTM parameters to any URL.
     */
    public static function utmUrl($url, $content, $campaign = 'upgrade_pro')
    {
        return add_query_arg([
            'utm_source'   => 'ninja-tables',
            'utm_medium'   => static::active() ? 'pro_plugin' : 'free_plugin',
            'utm_campaign' => $campaign,
            'utm_content'  => $content,
            'utm_term'     => NINJA_TABLES_VERSION,
        ], $url);
    }
}
