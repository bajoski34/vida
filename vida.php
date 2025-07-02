<?php
/**
 * Plugin Name: Vida by VeendHQ
 * Plugin URI: https://veendhq.com/
 * Description: This plugin is the official plugin of vida by vendhq.
 * Version: 0.0.1
 * Author: VeendHQ
 * Author URI: https://veendhq.com/
 * Developer: VeendHQ Developers
 * Developer URI: https://docs.veendhq.com/
 * Text Domain: vidaveend
 * Domain Path: /languages
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Vida
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VIDA_PLUGIN_FILE' ) ) {
	define( 'VIDA_PLUGIN_FILE', __FILE__ );
}

/**
 * Add the Settings link to the plugin
 *
 * @param  array $links Existing links on the plugin page.
 *
 * @return array Existing links with our settings link added
 */
function vida_plugin_action_links( array $links ): array {

	$vida_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-admin&path=%2Fvida' ) );
	array_unshift( $links, "<a title='Vida Settings Page' href='$vida_settings_url'>Setup</a>" );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'vida_plugin_action_links' );

/**
 * Initialize Vida.
 */
function vida_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! class_exists( 'Vida' ) ) {
		include_once dirname( VIDA_PLUGIN_FILE ) . '/inc/class-vida.php';
		// Global for backwards compatibility.
		$GLOBALS['vida'] = Vida::instance();
	}
}

add_action( 'plugins_loaded', 'vida_bootstrap', 99 );

/**
 * Register the admin JS.
 */
function vida_add_extension_register_script() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) && version_compare( WC_VERSION, '6.3', '<' ) && ! \Automattic\WooCommerce\Admin\Loader::is_admin_or_embed_page() ) {
		return;
	}

	if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) && version_compare( WC_VERSION, '6.3', '>=' ) && ! \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page() ) {
		return;
	}

	$script_path       = '/build/settings.js';
	$script_asset_path = dirname( VIDA_PLUGIN_FILE ) . '/build/settings.asset.php';
	$script_asset      = file_exists( $script_asset_path )
		? require_once $script_asset_path
		: array(
			'dependencies' => array(),
			'version'      => VIDA_VERSION,
		);

	wp_register_script(
		'vida-admin-js',
		plugins_url( 'build/settings.js', VIDA_PLUGIN_FILE ),
		array_merge( array( 'wp-element', 'wp-data', 'moment', 'wp-api' ), $script_asset['dependencies'] ),
		$script_asset['version'],
		true
	);

	$vida_fallback_settings = array(
		'enabled'            => 'no',
		'go_live'            => 'no',
		'title'              => 'Vida',
		'test_client_id'          => '401f1296-b902-4303-b437-e685af9f6313',
		'test_client_secret'      => 'd4b8c9ed-37dd-4e19-ba7a-4f7a2376ceb3-19a0bb77-1bf9-4f92-9621-e49b58223ad0-3dae54d8-a53e-498d-b022-71e1defb1cd6',
		'live_client_id'          => '401f1296-b902-4303-b437-e685af9f6313',
		'live_client_secret'      => 'd4b8c9ed-37dd-4e19-ba7a-4f7a2376ceb3-19a0bb77-1bf9-4f92-9621-e49b58223ad0-3dae54d8-a53e-498d-b022-71e1defb1cd6',
		'autocomplete_order' => 'no',
	);

	$vida_default_settings = get_option( 'woocommerce_vida_settings', $vida_fallback_settings );

	wp_localize_script(
		'vida-admin-js',
		'vidaData',
		array(
			'asset_plugin_url' => plugins_url( '', VIDA_PLUGIN_FILE ),
			'asset_plugin_dir' => plugins_url( '', VIDA_PLUGIN_DIR ),
			'vida_logo'      => plugins_url( 'assets/img/Vida-Logo3.png', VIDA_PLUGIN_FILE ),
			'vida_defaults'  => $vida_default_settings,
			'vida_webhook'   => WC()->api_request_url( 'Vida_Payment_Webhook' ),
		)
	);

	wp_enqueue_script( 'vida-admin-js' );

	wp_register_style(
		'vida_admin_css',
		plugins_url( 'assets/admin/style/index.css', VIDA_PLUGIN_FILE ),
		array(),
		VIDA_VERSION
	);

	wp_enqueue_style( 'vida_admin_css' );
}

add_action( 'admin_enqueue_scripts', 'vida_add_extension_register_script' );


/**
 * Register the Vida payment gateway for WooCommerce Blocks.
 *
 * @return void
 */
function vida_woocommerce_blocks_support() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( VIDA_PLUGIN_FILE ) . '/inc/block/class-vida-block-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

				$payment_method_registry->register( new Vida_Block_Support() );
			}
		);
	}
}

// add woocommerce block support.
add_action( 'woocommerce_blocks_loaded', 'vida_woocommerce_blocks_support' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);