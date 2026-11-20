<?php

namespace NinjaTables\App\Hooks\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NinjaTables\App\Modules\Lead\OptinPrompt;
use NinjaTables\App\App;
use NinjaTables\App\Helper\Pro;
use NinjaTables\App\Services\Vite;
use NinjaTables\App\Modules\I18nStrings;
use NinjaTables\Framework\Support\Arr;
use NinjaTables\Framework\Support\Sanitizer;

class AdminMenuHandler
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_head', [$this, 'printThemeClass']);
    }

    public function add()
    {
        global $submenu;
        $capability = ninja_table_admin_role();

        if (!$capability) {
            return;
        }
        // Top-level page
        $menuName = __('Ninja Tables', 'ninja-tables');
        if (Pro::active()) {
            $menuName .= ' Pro';
        }

        add_menu_page(
            $menuName,
            $menuName,
            $capability,
            'ninja_tables',
            [$this, 'render'],
            $this->getMenuIcon(),
            6
        );

        $submenu['ninja_tables']['all_tables'] = array(
            __('Tables', 'ninja-tables'),
            $capability,
            'admin.php?page=ninja_tables#/',
            '',
            'ninja_tables_all_tables'
        );

        $submenu['ninja_tables']['create_table'] = array(
            __('Create Table', 'ninja-tables'),
            $capability,
            'admin.php?page=ninja_tables#/create',
            '',
            'ninja_tables_create_table'
        );

        $submenu['ninja_tables']['import'] = array(
            __('Import', 'ninja-tables'),
            $capability,
            'admin.php?page=ninja_tables#/tools',
            '',
            'ninja_table_import_menu'
        );

        if (!defined('NINJA_CHARTS_VERSION')) {
            $submenu['ninja_tables']['ninja_charts'] = array(
                __('Charts', 'ninja-tables'),
                $capability,
                'admin.php?page=ninja_tables#/charts'
            );
        } else {
            $submenu['ninja_tables']['ninja_charts'] = array(
                __('Charts', 'ninja-tables'),
                $capability,
                'admin.php?page=ninja-charts#/chart-list'
            );
        }

        if (!Pro::active()) {
            $getPro = __('Get Pro', 'ninja-tables');
            $submenu['ninja_tables']['upgrade_pro'] = array(
                '<span style="color:#f39c12;">' . $getPro . '</span>',
                $capability,
                Pro::upgradeUrl('sidebar_cta'),
                '',
                'ninja_table_upgrade_menu'
            );
        } elseif (Pro::sortable()) {
            $license = get_option('_ninjatables_pro_license_status');
            if ($license != 'valid' && is_multisite()) {
                $license = get_network_option(get_main_network_id(), '_ninjatables_pro_license_status');
            }

            if ($license != 'valid') {
                $text = 'Activate License';
                if ($license == 'expired') {
                    $text = 'Renew License';
                }

                $submenu['ninja_tables']['activate_license'] = array(
                    '<span style="color:#f39c12;">' . $text . '</span>',
                    $capability,
                    'admin.php?page=ninja_tables#/tools/licensing',
                    '',
                    'ninja_table_license_menu'
                );
            }
        }

        $submenu['ninja_tables']['settings'] = array(
            __('Settings', 'ninja-tables'),
            $capability,
            'admin.php?page=ninja_tables#/tools/default_table_appearance',
            '',
            'ninja_tables_settings'
        );

        $submenu['ninja_tables']['help'] = array(
            __('Help', 'ninja-tables'),
            $capability,
            'admin.php?page=ninja_tables#/help',
            '',
            'ninja_tables_help'
        );

        $submenu = apply_filters('ninja_tables/add_submenu', $submenu, $capability);
    }

    public function render()
    {
        $config = App::getInstance('config');

        $name = $config->get('app.name');

        $slug = $config->get('app.slug');

        $baseUrl = apply_filters('ninja_tables/fluent_connector_base_url', admin_url('admin.php?page=' . $slug . '#/'));

        $menuItems = [
            [
                'key'       => 'dashboard',
                'label'     => __('Dashboard', 'ninja-tables'),
                'permalink' => $baseUrl
            ]
        ];

        $app    = App::getInstance();
        $assets = $app['url.assets'];

        App::make('view')->render('admin.menu', [
            'name'      => $name,
            'slug'      => $slug,
            'menuItems' => $menuItems,
            'baseUrl'   => $baseUrl,
            'logo'      => $assets . 'images/logo.svg',
        ]);
    }

    public function enqueueAssets()
    {
       $page = Sanitizer::sanitizeTextField(Arr::get($_GET, 'page')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page === 'ninja_tables') {
            $this->enqueueStyles();
            $this->enqueueScripts();
        }
    }

    /**
     * No-flash theme class for the shared Fluent admin theme.   @fluent-theme-toolkit v1
     *
     * The admin app marks <body> once it runs, which is after the first paint,
     * so a dark-mode user would see a white page first. This reads the shared
     * key and marks <html> before anything is drawn. It has to be inline: a
     * file is a request the paint waits on.
     *
     * The rules are in fluent-plugins-skills/dark-mode-unify/CONTRACT.md.
     */
    public function printThemeClass()
    {
        $page = Sanitizer::sanitizeTextField(Arr::get($_GET, 'page')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ($page !== 'ninja_tables') {
            return;
        }

        $script = <<<'JS'
(function(){try{var s=localStorage.getItem('fluent_theme_mode')||localStorage.getItem('ninjatable_theme')||'';
var m=s.split(':')[0];if(m!=='light'&&m!=='dark'){m=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}
if(m==='dark'){document.documentElement.classList.add('fluent_theme_dark');}}catch(e){}})();
JS;

        echo '<script id="ninja-tables-theme-class">' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    protected function getRestInfo($app)
    {
        $ns  = $app->config->get('app.rest_namespace');
        $ver = $app->config->get('app.rest_version');

        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $ver),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $ver
        ];
    }

    protected function getMenuIcon()
    {
        return 'data:image/svg+xml;base64,'
               . base64_encode(
                   '<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
              viewBox="0 0 80 80" xml:space="preserve">
                  <g>
                     <g>
                        <polyline points="0.6,51.2 18.4,51.2 18.4,38.6 0.6,38.6" fill="#ffffff" />
                        <path d="M0.6,63.1c0,1,0,1.9,0.2,2.8h17.6V53.5H0.6" fill="#ffffff"/>
                        <path d="M0.6,22.6" fill="#ffffff"/>
                        <path d="M0.6,21.5h78.8v-4.7c0-9-7.5-16.2-16.7-16.2H20.4h-3C8.1,0.5,0.6,7.8,0.6,16.7" fill="#ffffff"/>
                        <polyline points="0.6,36.3 18.4,36.3 18.4,23.8 0.6,23.8" fill="#ffffff"/>
                        <rect x="20.6" y="38.6" width="58.8" height="12.5" fill="#ffffff"/>
                        <rect x="20.6" y="23.8" width="58.8" height="12.4" fill="#ffffff"/>
                        <path d="M79.3,65.9c0.1-1.1,0.1-1.8,0.1-2.7v-9.7H20.6v12.4L79.3,65.9" fill="#ffffff"/>
                     </g>
                        <path d="M18.4,79.3L18.4,79.3v-11H1.5v0.1c2.2,6.4,8.5,11,15.9,11L18.4,79.3L18.4,79.3z" fill="#ffffff"/>
                        <path d="M78.6,68.3h-58v11v0.1h42.1C70.1,79.4,76.4,74.8,78.6,68.3C78.6,68.4,78.6,68.4,78.6,68.3" fill="#ffffff"/>
                  </g>
                </svg>'
               );
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {
        $slug = App::getInstance()->config->get('app.slug');

        // ninja-tables-vendor.css already contains every rule in
        // ninja-tables-admin.css - vendor.scss is that stylesheet plus a page
        // wrapper - so enqueueing both shipped the whole admin stylesheet
        // twice. Worse on RTL sites: the LTR sheet loaded after the RTL twin
        // at equal specificity and undid part of the mirroring.
        $vendorSrc = is_rtl() ? 'css/ninja-tables-vendor-rtl.css' : 'css/ninja-tables-vendor.css';

        Vite::enqueueStyle($slug . '_element_plus', 'css/ninja-tables-element-plus.css');
        Vite::enqueueStyle($slug . '_admin_app', $vendorSrc);
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        $slug = $app->config->get('app.slug');

        $plugin_url = NINJA_TABLES_DIR_URL;

        if (function_exists('wp_enqueue_editor')) {
            $app->addFilter('user_can_richedit', function ($status) {
                return true;
            });
            wp_enqueue_editor();
            wp_enqueue_script('thickbox');
            wp_enqueue_script('editor');
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        Vite::enqueueScript($slug . '_admin_app', 'js/ninja-tables-boot.js', array('jquery'));

        $app->doAction('ninja_tables_loaded_boot_script');

        Vite::enqueueScript($slug, 'js/ninja-tables-admin.js', array('jquery'));

        $fluentUrl = admin_url('plugin-install.php?s=FluentForm&tab=search&type=term');

        $isInstalled   = defined('FLUENTFORM') || Pro::active();
        $dismissed     = false;
        $dismissedTime = get_option('_ninja_tables_plugin_suggest_dismiss');

        if ($dismissedTime) {
            if ((time() - intval($dismissedTime)) < 518400) {
                $dismissed = true;
            }
        } else {
            $dismissed = true;
            update_option('_ninja_tables_plugin_suggest_dismiss', time() - 345600);
        }

        $currentUser = wp_get_current_user();

        $leadStatus          = false;
        $reviewOptinStatus   = false;
        $cptName             = 'ninja-table';
        $tableCount          = wp_count_posts($cptName);
        $totalPublishedTable = 0;
        $totalTrashedTable   = 0;
        $publish             = property_exists($tableCount, "publish") ? (int) $tableCount->publish : 0;
        $trash               = property_exists($tableCount, "trash") ? (int) $tableCount->trash : 0;

        // Decided once, the first time this version opens the admin app: a site that already
        // has tables is upgrading (and gets the "what moved" banner); one with none is new.
        // Stored so that a new site does not turn into an "upgrade" after its first table.
        $installAudience = get_option('ninja_tables_install_audience');
        if ($installAudience !== 'upgrade' && $installAudience !== 'new') {
            $installAudience = ($publish + $trash) > 0 ? 'upgrade' : 'new';
            update_option('ninja_tables_install_audience', $installAudience, false);
        }

        // Email + usage opt-in (OptinCard.vue). While its Tables-list card shows, the review
        // prompt waits, so only one ask is on screen at a time.
        $optinState = OptinPrompt::state($publish);
        $leadStatus = !empty($optinState['show_on_tables']);

        if ($tableCount && $publish > 2 && !$leadStatus) {
            $reviewOptinStatus = $app->applyFilters('ninja_tables_show_review_optin', $reviewOptinStatus);
        }

        if ($tableCount && $publish > 0) {
            $totalPublishedTable = $publish;
        }

        if ($tableCount && $trash > 0) {
            $totalTrashedTable = $trash;
        }

        $hasFluentFrom       = defined('FLUENTFORM_VERSION');
        $isFluentFromUpdated = false;
        $hasTablePress       = defined('TABLEPRESS_ABSPATH');
        $hasSupsystic        = defined('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME');

        // check for right version
        if ($hasFluentFrom) {
            if ($fluentVersionCompare = version_compare(FLUENTFORM_VERSION, '1.7.4') >= 1) {
                $isFluentFromUpdated = true;
            }
        }

        // Let's deregister existing vuejs by other devs
        // Other devs should not regis
        $app->addAction('admin_print_scripts', function () {
            wp_dequeue_script('vuejs');
            wp_dequeue_script('vue');
        });

        if (current_user_can('manage_options')) {
            $isAdmin = 'yes';
        } else {
            $isAdmin = 'no';
        }


        wp_localize_script($slug . '_admin_app', 'ninja_table_admin', array(
            'slug'                     => $slug,
            'nonce'                    => wp_create_nonce($slug),
            'rest'                     => $this->getRestInfo($app),
            'brand_logo'               => $this->getMenuIcon(),
            'asset_url'                => $assets,
            'me'                       => [
                'id'        => $currentUser->ID,
                'full_name' => trim($currentUser->first_name . ' ' . $currentUser->last_name),
                'email'     => $currentUser->user_email
            ],
            'img_url'                  => $assets . "img/",
            'plugin_version'           => NINJA_TABLES_VERSION,
            'fluentform_url'           => $fluentUrl,
            'fluent_wp_url'            => 'https://wordpress.org/plugins/fluentform/',
            'fluent_form_icon'         => function_exists('ninjaTablesGetFluentFormMenuIcon') ? ninjaTablesGetFluentFormMenuIcon(
            ) : '',
            'dismissed'                => $dismissed,
            'optin'                    => $optinState,
            'is_upgrade_install'       => $installAudience === 'upgrade',
            'welcome_banner_dismissed' => (bool) get_option('ninja_tables_welcome_banner_dismissed'),
            'show_review_dialog'       => $reviewOptinStatus,
            'current_user_name'        => $currentUser->display_name,
            'isInstalled'              => $isInstalled,
            'hasPro'                   => Pro::active(),
            'hasFluentForm'            => $hasFluentFrom,
            'hasNinjaForms'            => function_exists('Ninja_Forms'), // the Ninja Forms entries source is listed only when it's active
            'hasGravityForms'          => class_exists('GFAPI'), // the Gravity Forms entries source is listed only when it's active
            'hasWPForms'               => function_exists('wpforms') && class_exists('WPForms_Entry_Handler'), // WPForms Pro only: Lite keeps no entries
            'hasTablePress'            => $hasTablePress,
            'hasSupsystic'             => $hasSupsystic,
            'maxUploadSize'            => size_format(wp_max_upload_size()), // shown next to the Excel import picker
            'maxUploadBytes'           => wp_max_upload_size(),
            'canExportXlsx'            => class_exists('ZipArchive'), // Excel export needs the PHP Zip extension
            // Pro versions with a frontend Excel button return true; older Pro would draw
            // the button with no click handler, so the setting stays hidden for them.
            'hasXlsxFrontendButton'    => (bool) apply_filters('ninja_tables_frontend_xlsx_button_supported', false),
            'isFluentFormUpdated'      => $isFluentFromUpdated,
            'hasAdvancedFilters'       => class_exists('NinjaTablesPro\App\Hooks\Handlers\CustomFilterHandler'),
            'hasSortable'              => Pro::sortable(),
            'ace_path_url'             => $assets . "libs/ace",
            'upgradeGuide'             => 'https://wpmanageninja.com/r/docs/ninja-tables/how-to-install-and-upgrade/#upgrade',
            'hasValidLicense'          => get_option('_ninjatables_pro_license_status'),
            'i18n'                     => I18nStrings::getStrings(),
            'locale'                   => get_user_locale(),
            'published_tables'         => $totalPublishedTable,
            'trashed_tables'           => $totalTrashedTable,
            'preview_required_scripts' => array(
                $assets . "css/ninjatables-public.css",
                $assets . "libs/footable/js/footable.min.js",
                includes_url( '/js/dist/vendor/moment.min.js'),
                $assets . "js/ninja-tables-footable.js",
            ),
            'activated_features'       => $app->applyFilters('ninja_table_activated_features', array(
                'default_tables'    => true,
                'fluentform_tables' => true
            )),
            'nt_integrity'             => $this->getIntegrity(),
            'admin_notices'            => $app->applyFilters('ninja_dashboard_notices', []),
            'has_sql_permission'       => $app->applyFilters('ninja_table_sql_permission', $isAdmin),
            'prefered_thumb'           => $app->applyFilters('ninja_table_prefered_thumb', 'medium'),
            'has_woocommerce'          => defined('WC_PLUGIN_FILE'),
            'has_fluentCart'          => defined('FLUENTCART_VERSION'),
            'license_status'           => get_option('_ninjatables_pro_license_status'),
            'ninja_charts_url'         => defined('NINJA_CHARTS_VERSION') ? self_admin_url(
                'admin.php?page=ninja-charts#/chart-list'
            ) : null,
            'ninja_table_admin_nonce' => wp_create_nonce('ninja_table_admin_nonce'),
            'ninja_tables_pro_url'    => Pro::url(),
            'max_upload_file_size'    => apply_filters('ninja_tables/max_upload_file_size', 1024),
            'dt_preview_required_scripts' => array(
                $assets . "libs/datatables/datatables.min.css",
                $assets . "css/ninjatables-datatables.css",
                $assets . "libs/datatables/responsive.dataTables.min.css",
                NINJA_TABLES_DIR_URL . "assets/css/ninjatables-datatables-responsive.css",
                $assets . "libs/datatables/datatables.min.js",
                $assets . "libs/datatables/dataTables.responsive.min.js",
                $assets . "js/ninja-tables-datatables.js",
            ),
            'dt_ajax_url'             => admin_url('admin-ajax.php')
        ));

        // Elementor plugin have a bug where they throw error to parse #url, and I really don't know why they want to parse
        // other plugin's page's uri. They should fix it.
        // For now I am de-registering their script in ninja-table admin pages.
        wp_deregister_script('elementor-admin-app');

        // These last two line is for dumb devs who enqueue their scripts unversally
        // People should think what they are writing in their code
        wp_dequeue_script('vue');
        wp_dequeue_script('vuejs');

        // We are gonna dequeue every other scripts on our pages.
        add_action('wp_print_scripts', function () {
            if (is_admin()) {
                $skip = apply_filters('ninja_table_skip_no_confict', false);

                if ($skip) {
                    return;
                }

                global $wp_scripts;
                $pluginUrl = plugins_url();
                foreach ($wp_scripts->queue as $script) {
                    $src = $wp_scripts->registered[$script]->src;

                    if (strpos($src, $pluginUrl) !== false && !strpos($src, 'ninja-tables') !== false) {
                        wp_dequeue_script($wp_scripts->registered[$script]->handle);
                    }
                }
            }
        }, 1);

        /*
         * This script only for resolve the conflict of lodash and underscore js
         * Resolved the issue of media uploader specially for image upload
         */
        wp_add_inline_script($slug, $this->getInlineScript(), 'after');
    }

    private function getIntegrity()
    {
        if (Pro::active()) {
            if (is_multisite()) {
                return 'valid';
            }
            $status = get_option('_ninjatables_pro_license_status');
            if (is_multisite() && $status != 'valid') {
                $status = get_network_option(get_main_network_id(), '_ninjatables_pro_license_status');
            }
            if ($status == 'valid') {
                $key = get_option('_ninjatables_pro_license_key');
                if (is_multisite()) {
                    $key = get_network_option(get_main_network_id(), '_ninjatables_pro_license_key');
                }
                $length = strlen($key);
                if ($length < 20) {
                    return apply_filters('ninja_table_integrity', 'nope');
                }
            }
        }

        return apply_filters('ninja_table_integrity', 'valid');
    }

    public function getInlineScript()
    {
        return "
        function isLodash () {
        
        let isLodash = false;
    
        // If _ is defined and the function _.forEach exists then we know underscore OR lodash are in place
        if ( 'undefined' != typeof( _ ) && 'function' == typeof( _.forEach ) ) {
    
            // A small sample of some of the functions that exist in lodash but not underscore
            const funcs = [ 'get', 'set', 'at', 'cloneDeep' ];
    
            // Simplest if assume exists to start
            isLodash  = true;
    
            funcs.forEach( function ( func ) {
                // If just one of the functions do not exist, then not lodash
                isLodash = ( 'function' != typeof( _[ func ] ) ) ? false : isLodash;
            } );
        }
    
        if ( isLodash ) {
            // We know that lodash is loaded in the _ variable
            return true;
        } else {
            // We know that lodash is NOT loaded
            return false;
        }
    };
    
    if ( isLodash() ) {
        _.noConflict();
    }
    ";
    }
}
