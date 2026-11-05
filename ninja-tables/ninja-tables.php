<?php

defined( 'ABSPATH' ) || exit;

/**
Plugin Name: Ninja Tables – Responsive Table Builder for Data, Pricing & Product Tables
Description: The Easiest & Fastest Responsive Table Plugin on WordPress. Multiple templates, drag-&-drop live table builder, multiple color scheme, and styles.
Version: 5.5.0
Author: WPManageNinja LLC
Author URI: https://ninjatables.com/
Plugin URI: https://wpmanageninja.com/downloads/ninja-tables-pro-add-on/
License: GPL-2.0+
Text Domain: ninja-tables
Domain Path: /language
*/

require __DIR__ . '/vendor/autoload.php';

define('NINJA_TABLES_DIR_URL', plugin_dir_url(__FILE__));
define('NINJA_TABLES_DIR_PATH', plugin_dir_path(__FILE__));
define('NINJA_TABLES_VERSION', '5.5.0');
// Oldest Ninja Tables Pro this core is built for. An older Pro gets an "update Pro" notice.
define('NINJA_TABLES_MIN_PRO_VERSION', '5.5.0');
define('NINJA_TABLES_BASENAME', plugin_basename(__FILE__));

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));
