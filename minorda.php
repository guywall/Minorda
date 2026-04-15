<?php
/**
 * Plugin Name: Minorda
 * Plugin URI: https://example.com/
 * Description: Manage minimum quantity and value rules for WooCommerce products and product taxonomies.
 * Version: 1.0.4
 * Author: Webrankers
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: minorda
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCMR_VERSION', '1.0.4' );
define( 'WCMR_PLUGIN_FILE', __FILE__ );
define( 'WCMR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCMR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WCMR_PLUGIN_PATH . 'includes/class-wcmr-rule-engine.php';
require_once WCMR_PLUGIN_PATH . 'includes/class-wcmr-rule-repository.php';
require_once WCMR_PLUGIN_PATH . 'includes/class-wcmr-admin.php';
require_once WCMR_PLUGIN_PATH . 'includes/class-wcmr-frontend.php';
require_once WCMR_PLUGIN_PATH . 'includes/class-wcmr-plugin.php';

register_activation_hook( __FILE__, array( 'WCMR_Plugin', 'activate' ) );

WCMR_Plugin::instance();
