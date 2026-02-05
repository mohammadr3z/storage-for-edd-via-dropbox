<?php

/**
 * Plugin Name: Storage for EDD via Dropbox
 * Description: Enable secure cloud storage and delivery of your digital products through Dropbox for Easy Digital Downloads.
 * Version: 1.0.9
 * Author: mohammadr3z
 * Requires Plugins: easy-digital-downloads
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storage-for-edd-via-dropbox
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check for Composer autoload (required for Guzzle)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants
if (!defined('DBXE_PLUGIN_DIR')) {
    define('DBXE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('DBXE_PLUGIN_URL')) {
    define('DBXE_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('DBXE_VERSION')) {
    define('DBXE_VERSION', '1.0.9');
}

// Load plugin classes
require_once DBXE_PLUGIN_DIR . 'includes/class-dropbox-config.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-dropbox-client.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-dropbox-uploader.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-dropbox-downloader.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-media-library.php';
require_once DBXE_PLUGIN_DIR . 'includes/class-main-plugin.php';

// Initialize plugin on plugins_loaded
add_action('plugins_loaded', function () {
    new DBXE_DropboxStorage();
});

// Register activation/deactivation hooks for rewrite rules
register_activation_hook(__FILE__, array('DBXE_Admin_Settings', 'activatePlugin'));
register_deactivation_hook(__FILE__, array('DBXE_Admin_Settings', 'deactivatePlugin'));
