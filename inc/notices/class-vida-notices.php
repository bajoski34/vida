<?php
/**
 * Class Vida_Notices
 *
 * @package    Vida/WooCommerce
 * @subpackage Vida/WooCommerce/notices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vida Main Notice Class
 */
class Vida_Notices {
	/**
	 *  Woocommerce_not_installed
	 *
	 * @return void
	 */
	public function woocommerce_not_installed() {
		include_once dirname( VIDA_PLUGIN_FILE ) . '/inc/views/html-admin-missing-woocommerce.php';
	}

	/**
	 *  Woocommerce_wc_not_supported
	 *
	 * @return void
	 */
	public function woocommerce_wc_not_supported() {
		/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Vida requires WooCommerce %1$s or greater to be installed and activated. kindly upgrade to a higher version of WooCommerce. WooCommerce version %2$s is not supported.', 'vidaveend' ), esc_attr( VIDA_MIN_WC_VER ), esc_attr( WC_VERSION ) ) . '</strong></p></div>';
	}
}