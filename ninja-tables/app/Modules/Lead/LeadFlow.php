<?php

namespace NinjaTables\App\Modules\Lead;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The review prompt, plus the schedules behind the email opt-in and usage stats.
 *
 * The old one-click "Get important updates" opt-in (LeadOptIn, wpmn_api=product_users) is gone:
 * the email opt-in is SubscriberOptin and usage sharing is UsageStats, offered by OptinCard.vue
 * through OptinController.
 */
class LeadFlow
{
    const DAILY_HOOK = 'ninja_tables_daily_tasks';

    private $options = array();

    public function register()
    {
        add_action('init', [$this, 'boot']);

        // Opt-in delivery retries and the weekly usage report (both no-ops without consent)
        add_action(self::DAILY_HOOK, [$this, 'dailyTasks']);
        add_action(UsageStats::SEND_NOW_HOOK, function () {
            UsageStats::maybeSend(true);
        });
        add_action('admin_init', [$this, 'scheduleDaily']);
    }

    public function scheduleDaily()
    {
        UsageStats::migrateOldOptin();

        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK);
        }
    }

    public function dailyTasks()
    {
        UsageStats::migrateOldOptin();
        SubscriberOptin::maybeSend();
        UsageStats::maybeSend(); // weekly: it skips until 6 days have passed
    }

    /** Remove the schedules (plugin deactivation) */
    public static function unschedule()
    {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
        wp_clear_scheduled_hook(UsageStats::SEND_NOW_HOOK);
    }

    public function boot()
    {
        if (is_multisite() || ! current_user_can(ninja_table_admin_role())) {
            return;
        }

        $this->options = get_option('_ninja_table_lead_options', array());

        add_filter('ninja_tables_show_review_optin', array($this, 'reviewOptionStatus'));
        add_action('wp_ajax_ninja_table_review_consent', array($this, 'reviewOptinAction'));
    }

    public function reviewOptionStatus($status)
    {
        $reviewOption = new ReviewOptIn($this->options);
        if ($reviewOption->noticeable()) {
            return true;
        }

        return $status;
    }

    public function reviewOptinAction()
    {
        ninjaTablesValidateNonce();
        $status       = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $reviewOption = new ReviewOptIn($this->options);
        $reviewOption->doConsent($status);
        wp_send_json_success(array(
            'message' => __('Thank you', 'ninja-tables')
        ), 200);
    }
}
