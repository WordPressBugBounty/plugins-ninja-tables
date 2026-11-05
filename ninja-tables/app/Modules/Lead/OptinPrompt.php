<?php

namespace NinjaTables\App\Modules\Lead;

use NinjaTables\App\Helper\Pro;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Where and when the admin app offers the email + usage opt-in, and what it already knows.
 *
 * Two places only: a card under the Tables list (2+ tables, until answered or closed, then the
 * Fluent Forms promotion takes its place) and a card on the Help page (always, also the place to
 * change the choices later). Never an admin notice, never again once closed.
 */
class OptinPrompt
{
    // Time the Tables-list card was answered or closed; it never comes back after that
    const OPTION = '_ninja_tables_optin_prompt';

    const MIN_TABLES = 2;

    public static function canManage()
    {
        // Sharing site data is the site owner's call, not every role that can edit tables
        return current_user_can('manage_options');
    }

    public static function close()
    {
        update_option(self::OPTION, time(), false);
    }

    /**
     * The Tables-list card is never shown again to a site that answered or closed it, or that
     * answered the old "Get important updates" pop-up (yes or no). Old "yes" sites share usage
     * stats instead (UsageStats::migrateOldOptin()). The Help page still offers the card.
     */
    private static function answeredBefore()
    {
        if (get_option(self::OPTION)) {
            return true;
        }
        $old = get_option('_ninja_table_lead_options', []);

        return is_array($old) && !empty($old['lead_optin_status']) && in_array($old['lead_optin_status'], ['yes', 'no'], true);
    }

    /**
     * For window.ninja_table_admin.optin
     *
     * @param int $publishedTables
     */
    public static function state($publishedTables)
    {
        if (!self::canManage()) {
            return ['can_manage' => false, 'show_on_tables' => false];
        }

        $user       = wp_get_current_user();
        $optin      = SubscriberOptin::get();
        $subscribed = !empty($optin['email']);
        // The email opt-in exists to move free users to Pro: hidden while Pro is active
        $emailAllowed = !Pro::active();

        return [
            'can_manage'     => true,
            'email_allowed'  => $emailAllowed,
            'show_on_tables' => $emailAllowed && !$subscribed && !self::answeredBefore()
                && (int) $publishedTables >= self::MIN_TABLES,
            'subscribed'     => $subscribed ? [
                'email'  => $optin['email'],
                'status' => $optin['status'] ? $optin['status'] : 'queued',
            ] : null,
            'usage'          => UsageStats::consent(),
            // Pre-filled as a convenience; the admin sends only what is in the fields
            'defaults'       => [
                'full_name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name,
                'email'     => $user->user_email,
            ],
        ];
    }
}
