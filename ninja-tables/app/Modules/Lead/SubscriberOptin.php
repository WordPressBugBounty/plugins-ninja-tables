<?php

namespace NinjaTables\App\Modules\Lead;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin's "email me" opt-in. One request, sent once, retried until it lands.
 *
 * Contract: fluent-license-cf-proxy docs/plugin-subscriber-optin-integration.md (section 5.1 is
 * the class this copies). The Worker hands the lead to the product site's FluentCRM and sends a
 * double opt-in email; Pro is detected there from license calls, so nothing here knows about Pro.
 */
class SubscriberOptin
{
    const ENDPOINT = 'https://fluentapi.wpmanageninja.com/subscribe';

    const OPTION = '_ninja_tables_subscriber_optin';

    // Bump when the wording beside the email opt-in (OptinCard.vue, "consent" text) changes.
    const CONSENT_VERSION = '2026-11-c';

    // 30 days, not the guide's 7: the Worker entry may go live after this release ships, and a
    // refused retry (422) is one cheap request a day
    const GIVE_UP_AFTER = 30 * DAY_IN_SECONDS;

    const STATUSES = ['confirm_email', 'subscribed', 'queued'];

    /** The same opt-in: same address, consented at the same moment */
    private static function isSameOptin($stored, $sent)
    {
        return !empty($stored['email'])
            && strtolower($stored['email']) === strtolower($sent['email'])
            && (int) $stored['consented_at'] === (int) $sent['consented_at'];
    }

    public static function get()
    {
        $optin = get_option(self::OPTION, []);

        return is_array($optin) ? $optin : [];
    }

    /**
     * @param string $email    What the admin typed
     * @param string $fullName What the admin typed (never their profile name)
     * @param string $source   The placement the opt-in came from, for reporting (snake_case)
     * @return string|false 'confirm_email', 'subscribed' or 'queued'; false for a bad email
     */
    public static function optIn($email, $fullName = '', $source = '')
    {
        if (!is_email($email)) {
            return false;
        }

        $current = self::get();

        // Same address, already landed: nothing to send again
        if (!empty($current['sent_at']) && strtolower($current['email']) === strtolower($email)) {
            return $current['status'];
        }

        update_option(self::OPTION, [
            'email'           => $email,
            'full_name'       => $fullName,
            'source'          => preg_match('/^[a-z][a-z0-9_]{0,63}$/', $source) ? $source : '',
            'consent_version' => self::CONSENT_VERSION,
            'consented_at'    => time(),
            'sent_at'         => 0,
            'status'          => '',
        ], false);

        $status = self::maybeSend();

        // Our API was unreachable. The daily task retries; the admin still gets an answer.
        return $status ?: 'queued';
    }

    /**
     * Stops future sends. Unsubscribing is the link in every email.
     */
    public static function optOut()
    {
        delete_option(self::OPTION);
    }

    /**
     * Runs inline from optIn(), and again from the daily task until it lands.
     *
     * @return string|false the response's status, or false if it did not land
     */
    public static function maybeSend()
    {
        $optin = self::get();

        if (empty($optin['email']) || !empty($optin['sent_at'])) {
            return false;
        }

        if (time() - (int) $optin['consented_at'] > self::GIVE_UP_AFTER) {
            return false;
        }

        $body = [
            'product'         => 'ninja-tables',
            'product_version' => NINJA_TABLES_VERSION,
            // home_url(), not site_url(): it must equal what Pro's licensing sends.
            'site_url'        => home_url(),
            'email'           => $optin['email'],
            // The Worker splits it into first and last name for FluentCRM.
            'full_name'       => $optin['full_name'],
            'consent_version' => $optin['consent_version'],
            'locale'          => get_user_locale(),
        ];

        if (!empty($optin['source'])) {
            $body['source'] = $optin['source'];
        }

        $response = wp_remote_post(self::ENDPOINT, [
            // The Worker gives the CRM 8s and then answers 'queued', so 15s is plenty.
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'cookies' => [],
        ]);

        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        // Transport errors, 429 and 5xx retry from the daily task; so does any other 4xx here,
        // which is harmless (attempts stop after GIVE_UP_AFTER) and simpler than a branch.
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $status  = is_array($decoded) && !empty($decoded['status']) ? sanitize_key($decoded['status']) : 'queued';

        // Anything we don't recognise reads as 'queued': the Worker has it either way.
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'queued';
        }

        // The request can take seconds. If the admin changed or removed the email meanwhile,
        // their newer choice wins: record the delivery only on the opt-in that was sent.
        // Read the option fresh: the cached copy would not show another request's change
        wp_cache_delete(self::OPTION, 'options');
        if (!self::isSameOptin(self::get(), $optin)) {
            return $status;
        }

        $optin['sent_at'] = time();
        $optin['status']  = $status;
        update_option(self::OPTION, $optin, false);

        return $status;
    }
}
