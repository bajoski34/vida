<?php
/**
 * The file that defines the class that register vida as a paymnet gateway on the cart and checkout block.
 *
 * A class that defines a block type.
 *
 * @link       https://docs.veendhq.com/
 * @since      1.0.0
 *
 * @package    Vida/WooCommerce
 * @subpackage Vida/WooCommerce/block
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class Vida_Block_Support.
 *
 * @since 2.3.2
 * @extends AbstractPaymentMethodType
 * @package Vida
 */
final class Vida_Block_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'vida';

	/**
	 * Settings from the WP options table
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Initialize the Block.
	 *
	 * @inheritDoc
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vida_settings', array() );

		if ( version_compare( WC_VERSION, '6.9.1', '<' ) ) {
			// For backwards compatibility.
			if ( ! class_exists( 'Vida_Payment_Gateway' ) ) {
				require_once dirname( VIDA_PLUGIN_FILE ) . '/includes/class-vida-payment-gateway.php';
			}

			$this->gateway = new Vida_Payment_Gateway();
		} else {
			$gateways      = WC()->payment_gateways->payment_gateways();
			$this->gateway = $gateways[ $this->name ];
		}
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		if ( version_compare( WC_VERSION, '6.9.0', '>' ) ) {
			$gateways = WC()->payment_gateways->payment_gateways();

			if ( ! isset( $gateways[ $this->name ] ) ) {
				return false;
			}
		}

		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		return $this->gateway->supports;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'vida',
			'https://vida-dashboard-git-ft-agiletech-veendhq-engineering.vercel.app/vendor/vida-merchant.js',
			array(),
			VIDA_VERSION,
			true
		);

		$asset_path   = dirname( VIDA_PLUGIN_FILE ) . '/build/index.asset.php';
		$version      = VIDA_VERSION;
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-vida-blocks',
			VIDA_URL . '/build/index.js',
			array_merge( array( 'vida' ), $dependencies ),
			$version,
			true
		);

		wp_enqueue_style(
			'wc-vida-blocks',
			VIDA_URL . '/assets/css/vida.css',
			array(),
			VIDA_VERSION,
			false
		);

		wp_set_script_translations(
			'wc-vida-blocks',
			'vida'
		);

		return array(
			'wc-vida-blocks',
		);
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return array(
			'icons'         => $this->get_icons(),
			'supports'      => array_filter( $this->get_supported_features(), array( $this->gateway, 'supports' ) ),
			'isAdmin'       => is_admin(),
			'client_id'     => ( 'yes' === $this->settings['go_live'] ) ? $this->settings['live_client_id'] : $this->settings['test_client_id'],
			'client_secret' => ( 'yes' === $this->settings['go_live'] ) ? $this->settings['live_client_secret'] : $this->settings['test_client_secret'],
			'asset_url'     => plugins_url( 'assets', VIDA_PLUGIN_FILE ),
			'title'         => $this->settings['title'],
			'description'   => $this->settings['description'] ?? '',
		);
	}

	/**
	 * Returns an array of icons for the payment method.
	 *
	 * @return array
	 */
	private function get_icons(): array {
		$icons_src = array(
			'visa'       => array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/visa.svg',
				'alt' => __( 'Visa', 'vidaveend' ),
			),
			'amex'       => array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/amex.svg',
				'alt' => __( 'American Express', 'vidaveend' ),
			),
			'mastercard' => array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/mastercard.svg',
				'alt' => __( 'Mastercard', 'vidaveend' ),
			),
		);

		if ( 'USD' === get_woocommerce_currency() ) {
			$icons_src['discover'] = array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/discover.svg',
				'alt' => _x( 'Discover', 'Name of credit card', 'vidaveend' ),
			);
			$icons_src['jcb']      = array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/jcb.svg',
				'alt' => __( 'JCB', 'vidaveend' ),
			);
			$icons_src['diners']   = array(
				'src' => dirname( VIDA_PLUGIN_FILE ) . '/assets/img/diners.svg',
				'alt' => __( 'Diners', 'vidaveend' ),
			);
		}
		return $icons_src;
	}
}
