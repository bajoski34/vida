<?php
/**
 * Main Class of the Plugin.
 *
 * @package    Vida/WooCommerce
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Main Class.
 *
 * @since 1.0.0
 */
class Vida {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public string $version = '0.0.1';

	/**
	 * Plugin Instance.
	 *
	 * @var Vida|null
	 */
	public static ?Vida $instance = null;

	/**
	 * Vida Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init();
	}

	/**
	 * Main Instance.
	 */
	public static function instance(): Vida {
		self::$instance = is_null( self::$instance ) ? new self() : self::$instance;

		return self::$instance;
	}

	/**
	 * Define general constants.
	 *
	 * @param string      $name  constant name.
	 * @param string|bool $value constant value.
	 */
	private function define( string $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Define Vida Constants.
	 */
	private function define_constants() {
		$this->define( 'VIDA_VERSION', $this->version );
		$this->define( 'VIDA_MINIMUM_WP_VERSION', '5.8' );
		$this->define( 'VIDA_PLUGIN_URL', plugin_dir_url( VIDA_PLUGIN_FILE ) );
		$this->define( 'VIDA_PLUGIN_BASENAME', plugin_basename( VIDA_PLUGIN_FILE ) );
		$this->define( 'VIDA_PLUGIN_DIR', plugin_dir_path( VIDA_PLUGIN_FILE ) );
		$this->define( 'VIDA_DIR_PATH', plugin_dir_path( VIDA_PLUGIN_FILE ) );
		$this->define( 'VIDA_MIN_WC_VER', '6.9.1' );
		$this->define( 'VIDA_URL', trailingslashit( plugins_url( '/', VIDA_PLUGIN_FILE ) ) );
		$this->define( 'VIDA_ALLOWED_WEBHOOK_IP_ADDRESS', '99.80.58.253' );
		$this->define( 'VIDA_EPSILON', 0.01 );
	}

	/**
	 * Initialize the plugin.
	 * Checks for an existing instance of this class in the global scope and if it doesn't find one, creates it.
	 *
	 * @return void
	 */
	private function init() {
		$notices = new Vida_Notices();

		// Check if WooCommerce is Active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $notices, 'woocommerce_not_installed' ) );
			return;
		}

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		if ( version_compare( WC_VERSION, VIDA_MIN_WC_VER, '<' ) ) {
			add_action( 'admin_notices', array( $notices, 'woocommerce_wc_not_supported' ) );
			return;
		}

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action(
			'admin_print_styles',
			function () {
				// using admin_print_styles.
				$image_url = plugin_dir_url( VIDA_PLUGIN_FILE ) . 'assets/img/vida-30x30.png';
				echo '<style> .dashicons-vida {
						background-image: url("' . esc_url( $image_url ) . '");
						background-repeat: no-repeat;
						background-position: center; 
					}</style>';
			}
		);

		add_action( 'admin_menu', array( $this, 'add_wc_admin_menu' ) );
		$this->register_vida_wc_page_items();
		$this->register_payment_gateway();

		include_once VIDA_PLUGIN_DIR . 'inc/rest-api/class-vida-settings-rest-controller.php';
		$settings__endpoint = new Vida_Settings_Rest_Controller();
		add_action( 'rest_api_init', array( $settings__endpoint, 'register_routes' ) );
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {}

	/**
	 * Register the WooCommerce Settings Page.
	 *
	 * @since 1.0.0
	 */
	public function add_wc_admin_menu() {
		wc_admin_register_page(
			array(
				'id'       => 'vida-wc-page',
				'title'    => __( 'Vida', 'vidaveend' ),
				'path'     => '/vida',
				'nav_args' => array(
					'parent'       => 'woocommerce',
					'is_top_level' => true,
					'menuId'       => 'plugins',
				),
				'position' => 3,
				'icon'     => 'dashicons-vida',
			)
		);
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		// Include classes that can run on WP Freely.
		include_once dirname( VIDA_PLUGIN_FILE ) . '/inc/notices/class-vida-notices.php';
	}

	/**
	 * This handles actions on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$notices = new Vida_Notices();
			add_action( 'admin_notices', array( $notices, 'woocommerce_not_installed' ) );
		}
	}

	/**
	 * This handles actions on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Deactivation logic.
	}

	/**
	 * Handle Vida WooCommerce Page Items.
	 */
	public function register_vida_wc_page_items() {
		if ( ! method_exists( '\Automattic\WooCommerce\Admin\Features\Navigation\Menu', 'add_plugin_category' ) ||
			! method_exists( '\Automattic\WooCommerce\Admin\Features\Navigation\Menu', 'add_plugin_item' )
		) {
			return;
		}
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_category(
			array(
				'id'         => 'vida-root',
				'title'      => 'Vida',
				'capability' => 'view_woocommerce_reports',
			)
		);
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_item(
			array(
				'id'         => 'vida-1',
				'parent'     => 'vida-root',
				'title'      => 'Vida 1',
				'capability' => 'view_woocommerce_reports',
				'url'        => 'https://veendhq.com/',
			)
		);
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_item(
			array(
				'id'         => 'vida-2',
				'parent'     => 'vida-root',
				'title'      => 'Vida 2',
				'capability' => 'view_woocommerce_reports',
				'url'        => 'https://veendhq.com/',
			)
		);
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_category(
			array(
				'id'              => 'sub-menu',
				'parent'          => 'vida-root',
				'title'           => 'Vida Menu',
				'capability'      => 'view_woocommerce_reports',
				'backButtonLabel' => 'Vida',
			)
		);
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_item(
			array(
				'id'         => 'sub-menu-child-1',
				'parent'     => 'sub-menu',
				'title'      => 'Sub Menu Child 1',
				'capability' => 'view_woocommerce_reports',
				'url'        => 'https://veendhq.com',
			)
		);
		\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_item(
			array(
				'id'         => 'sub-menu-child-2',
				'parent'     => 'sub-menu',
				'title'      => 'Sub Menu Child 2',
				'capability' => 'view_woocommerce_reports',
				'url'        => 'https://veendhq.com/',
			)
		);
	}

	/**
	 * Register Vida as a Payment Gateway.
	 *
	 * @return void
	 */
	public function register_payment_gateway() {
		require_once dirname( VIDA_PLUGIN_FILE ) . '/inc/class-vida-payment-gateway.php';

		add_filter( 'woocommerce_payment_gateways', array( 'Vida', 'add_gateway_to_woocommerce_gateway_list' ), 99 );
	}

	/**
	 * Add the Gateway to WooCommerce
	 *
	 * @param  array $methods Existing gateways in WooCommerce.
	 *
	 * @return array Gateway list with our gateway added
	 */
	public static function add_gateway_to_woocommerce_gateway_list( array $methods ): array {

		$methods[] = 'Vida_Payment_Gateway';

		return $methods;
	}

	/**
	 * Add the Settings link to the plugin
	 *
	 * @param  array $links Existing links on the plugin page.
	 *
	 * @return array Existing links with our settings link added
	 */
	public static function plugin_action_links( array $links ): array {

		$vida_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=vida' ) );
		array_unshift( $links, "<a title='Vida Settings Page' href='$vida_settings_url'>Setup</a>" );

		return $links;
	}
}
