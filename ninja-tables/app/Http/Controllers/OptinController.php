<?php

namespace NinjaTables\App\Http\Controllers;

use NinjaTables\App\Modules\Lead\OptinPrompt;
use NinjaTables\App\Modules\Lead\SubscriberOptin;
use NinjaTables\App\Modules\Lead\UsageStats;
use NinjaTables\Framework\Http\Request\Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The email opt-in and the usage-sharing choices (OptinCard.vue on the Tables list and Help).
 */
class OptinController extends Controller
{
    private function guard()
    {
        if (!OptinPrompt::canManage()) {
            $this->json(['message' => __('Only a site administrator can change this.', 'ninja-tables')], 403);
        }
    }

    private function publishedTables()
    {
        $counts = wp_count_posts('ninja-table');

        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    /**
     * Email opt-in (when an email is given) plus the usage choices, from one card.
     */
    public function subscribe(Request $request)
    {
        $this->guard();

        $email    = sanitize_email((string) $request->get('email', ''));
        $fullName = sanitize_text_field((string) $request->get('full_name', ''));
        $source   = sanitize_key((string) $request->get('source', ''));

        $status = null;
        if ($email !== '') {
            if (!OptinPrompt::state($this->publishedTables())['email_allowed']) {
                $this->json(['message' => __('Email updates are not offered while Ninja Tables Pro is active.', 'ninja-tables')], 422);
            }
            $status = SubscriberOptin::optIn($email, $fullName, $source);
            if ($status === false) {
                $this->json(['message' => __('Please enter a valid email address.', 'ninja-tables')], 422);
            }
        }

        if ($request->get('share_usage') !== null) {
            UsageStats::saveConsent($request->get('share_usage') === 'yes' ? 'yes' : 'no', $request->get('share_plugins') === 'yes' ? 'yes' : 'no');
        }

        OptinPrompt::close();

        $this->json([
            'status'  => $status,
            'message' => $this->statusMessage($status, $email),
            'optin'   => OptinPrompt::state($this->publishedTables()),
        ], 200);
    }

    /** Usage choices alone (Help page) */
    public function saveUsage(Request $request)
    {
        $this->guard();

        UsageStats::saveConsent($request->get('share_usage') === 'yes' ? 'yes' : 'no', $request->get('share_plugins') === 'yes' ? 'yes' : 'no');

        $this->json([
            'message' => __('Your choice is saved.', 'ninja-tables'),
            'optin'   => OptinPrompt::state($this->publishedTables()),
        ], 200);
    }

    /** "No thanks" or the close button on the Tables list card: never shown there again */
    public function dismiss()
    {
        $this->guard();
        OptinPrompt::close();

        $this->json(['optin' => OptinPrompt::state($this->publishedTables())], 200);
    }

    /** Stop sending from this site. Unsubscribing itself is the link in every email. */
    public function stopEmails()
    {
        $this->guard();
        SubscriberOptin::optOut();

        $this->json([
            'message' => __('This site will not send your email again. To stop the emails, use the unsubscribe link in any of them.', 'ninja-tables'),
            'optin'   => OptinPrompt::state($this->publishedTables()),
        ], 200);
    }

    private function statusMessage($status, $email)
    {
        if ($status === 'subscribed') {
            return __("You're subscribed. Thanks!", 'ninja-tables');
        }
        if ($status === 'confirm_email') {
            /* translators: %s: the email address the admin entered */
            return sprintf(__('Check your inbox at %s to confirm.', 'ninja-tables'), $email);
        }
        if ($status) {
            return __('Thanks! A confirmation email is on its way.', 'ninja-tables');
        }

        return __('Your choice is saved.', 'ninja-tables');
    }
}
