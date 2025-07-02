<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://docs.veendhq.com/
 * @since      0.0.1
 *
 * @package    Vida/WooCommerce
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/util/class-vida-logger.php';

/**
 * Vida x WooCommerce Integration Class.
 */
class Vida_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Base url
	 *
	 * @var string the api base url.
	 */
	protected string $base_url;

	/**
	 * Public Key
	 *
	 * @var string the client ID.
	 */
	protected string $client_id;
	/**
	 * Secret Key
	 *
	 * @var string the client Secret.
	 */
	protected string $client_secret;
	/**
	 * Test Public Key
	 *
	 * @var string the test public key.
	 */
	private string $test_client_id;
	/**
	 * Test Secret Key.
	 *
	 * @var string the test secret key.
	 */
	private string $test_client_secret;
	/**
	 * Live Public Key
	 *
	 * @var string the live public key
	 */
	private string $live_client_id;
	/**
	 * Go Live Status.
	 *
	 * @var string the go live status.
	 */
	private string $go_live;
	/**
	 * Live Secret Key.
	 *
	 * @var string the live secret key.
	 */
	private string $live_client_secret;
	/**
	 * Auto Complete Order.
	 *
	 * @var false|mixed|null
	 */
	private $auto_complete_order;
	/**
	 * Logger
	 *
	 * @var WC_Logger the logger.
	 */
	private Vida_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->base_url           = "https://vida-dev.veendhq.com";
		$this->id                 = 'vida';
		$this->icon               = plugins_url( 'assets/img/vida.png', VIDA_PLUGIN_FILE );
		$this->has_fields         = false;
		$this->method_title       = 'Vida';
		$this->method_description = 'Vida ' . __( 'A BNPL options for customer to lend to checkout and pay later (Only NGN available at the moment).', 'vidaveend' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title               = $this->get_option( 'title' );
		$this->description         = $this->get_option( 'description' );
		$this->enabled             = $this->get_option( 'enabled' );
		$this->test_client_id      = $this->get_option( 'test_client_id' );
		$this->test_client_secret  = $this->get_option( 'test_client_secret' );
		$this->live_client_id      = $this->get_option( 'live_client_id' );
		$this->live_client_secret  = $this->get_option( 'live_client_secret' );
		$this->auto_complete_order = $this->get_option( 'autocomplete_order' );
		$this->go_live             = $this->get_option( 'go_live' );
		$this->supports            = array(
			'products'
		);

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_vida_payment_gateway', array( $this, 'vida_verify_payment' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_vida_payment_webhook', array( $this, 'vida_notification_handler' ) );

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		$this->client_id = $this->test_client_id;
		$this->client_secret = $this->test_client_secret;

		if ( 'yes' === $this->go_live ) {
			$this->client_id = $this->live_client_id;
			$this->client_secret = $this->live_client_secret;
		}

		$this->logger = Vida_Logger::instance();
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * WooCommerce admin settings override.
	 */
	public function admin_options() {
		?>
		
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label><?php esc_attr_e( 'Webhook Instruction', 'vidaveend' ); ?></label>
				</th>
				<td class="forminp forminp-text">
					<p class="description">
						<?php esc_attr_e( 'Please add this webhook URL and paste on the webhook section on your dashboard', 'vidaveend' ); ?><strong style="color: blue"><pre><code><?php echo esc_url( WC()->api_request_url( 'Vida_Payment_Webhook' ) ); ?></code></pre></strong><a href="https://merchant.vida.com/merchant/settings" target="_blank">Merchant Account</a>
					</p>
				</td>
			</tr>
			<?php
				$this->generate_settings_html();
			?>
		</table>
		<?php
	}

	/**
	 * Initial gateway settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$this->form_fields = array(

			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'vidaveend' ),
				'label'       => __( 'Enable Vida', 'vidaveend' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Vida as a payment option on the checkout page', 'vidaveend' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Payment method title', 'vidaveend' ),
				'type'        => 'text',
				'description' => __( 'Optional', 'vidaveend' ),
				'default'     => 'Vida',
			),
			'description'        => array(
				'title'       => __( 'Payment method description', 'vidaveend' ),
				'type'        => 'text',
				'description' => __( 'Optional', 'vidaveend' ),
				'default'     => 'Powered by VeendHQ.',
			),
			'test_client_id'    => array(
				'title'       => __( 'Test Client ID', 'vidaveend' ),
				'type'        => 'text',
				'description' => __( 'Required! Enter your Vida test client_id here', 'vidaveend' ),
				'default'     => '',
			),
			'test_client_secret'    => array(
				'title'       => __( 'Test Client Secret', 'vidaveend' ),
				'type'        => 'password',
				'description' => __( 'Required! Enter your Vida test client secret here', 'vidaveend' ),
				'default'     => '',
			),
			'live_client_id'    => array(
				'title'       => __( 'Live Client Id', 'vidaveend' ),
				'type'        => 'text',
				'description' => __( 'Required! Enter your Vida live client id here', 'vidaveend' ),
				'default'     => '',
			),
			'live_client_secret'    => array(
				'title'       => __( 'Live Client Secret', 'vidaveend' ),
				'type'        => 'password',
				'description' => __( 'Required! Enter your Vida live client Secret here', 'vidaveend' ),
				'default'     => '',
			),
			'autocomplete_order' => array(
				'title'       => __( 'Autocomplete Order After Payment', 'vidaveend' ),
				'label'       => __( 'Autocomplete Order', 'vidaveend' ),
				'type'        => 'checkbox',
				'class'       => 'vida-autocomplete-order',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'vidaveend' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'go_live'            => array(
				'title'       => __( 'Mode', 'vidaveend' ),
				'label'       => __( 'Live mode', 'vidaveend' ),
				'type'        => 'checkbox',
				'description' => __( 'Check this box if you\'re using your live keys.', 'vidaveend' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Order id
	 *
	 * @param int $order_id  Order id.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		// For Redirect Checkout.
		// if ( 'redirect' === $this->payment_style ) {
		// 	return $this->process_redirect_payments( $order_id );
		// }

		// For inline Checkout.
		$order = wc_get_order( $order_id );

		$custom_nonce = wp_create_nonce();
		$this->logger->info( 'Rendering Payment Modal' );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ) . "&_wpnonce=$custom_nonce",
		);
	}

	/**
	 * Get Secret Key
	 *
	 * @return string
	 */
	public function get_secret_key(): string {
		return $this->secret_key;
	}

	/**
	 * Order id
	 *
	 * @param int $order_id  Order id.
	 *
	 * @return array|void
	 */
	public function process_redirect_payments( $order_id ) {
		//TODO: Future implementation a secure version of the current implementation.
	}

	/**
	 * Handles admin notices
	 *
	 * @return void
	 */
	public function admin_notices(): void {

		if ( 'yes' === $this->enabled ) {

			if ( empty( $this->public_key ) || empty( $this->secret_key ) ) {

				$message = sprintf(
				/* translators: %s: url */
					__( 'For Vida on appear on checkout. Please <a href="%s">set your Vida Oauth Credentials are needed</a> to be able to accept payments.', 'vidaveend' ),
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vida' ) )
				);
			}
		}
	}

	/**
	 * Checkout receipt page
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function receipt_page( int $order_id ) {
		$order = wc_get_order( $order_id );
	}

	/**
	 * Loads (enqueue) static files (js & css) for the checkout page
	 *
	 * @return void
	 */
	public function payment_scripts() {

		// Load only on checkout page.
		if ( ! is_checkout_pay_page() && ! isset( $_GET['key'] ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		$expiry_message = sprintf(
			/* translators: %s: shop cart url */
			__( 'Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'vidaveend' ),
			esc_url( wc_get_page_permalink( 'shop' ) )
		);

			$nonce_value = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

			$order_key = urldecode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) );
			$order_id  = absint( get_query_var( 'order-pay' ) );

			$order = wc_get_order( $order_id );

			$complete_order_items = [];

			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product(); // WC_Product object
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();
				$total = $item->get_total();
				$sku = $product ? $product->get_sku() : '';

				$complete_order_items[] = [
					'name' => $product_name,
					'unitPrice' => $total,
					'quantity' => $quantity
				];
			}

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value ) ) {

			WC()->session->set( 'refresh_totals', true );
			wc_add_notice( __( 'We were unable to process your order, please try again.', 'vidaveend' ) );
			wp_safe_redirect( $order->get_cancel_order_url() );
			return;
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$vida_inline_link = 'https://vida-dashboard-git-ft-agiletech-veendhq-engineering.vercel.app/vendor/vida-merchant.js';
		$checkout_frontend_script = 'assets/js/checkout.js';
		if ( 'yes' === $this->go_live ) {
			$vida_inline_link = 'https://app.mycreditprofile.me/vendor/vida-merchant.js';
			$checkout_frontend_script = 'assets/js/checkout.min.js';
		}

		wp_enqueue_script( 'vidaveend', $vida_inline_link, array( 'jquery' ), VIDA_VERSION, false );
		wp_enqueue_script( 'vida_js', plugins_url( $checkout_frontend_script, VIDA_PLUGIN_FILE ), array( 'jquery', 'vidaveend' ), VIDA_VERSION, false );

		$payment_args = array();

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {
			$email         = $order->get_billing_email();
			$amount        = $order->get_total();
			$txnref        = 'WOO_' . $order_id . '_' . time();
			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();
			$currency      = $order->get_currency();
			$custom_nonce  = wp_create_nonce();
			$redirect_url  = WC()->api_request_url( 'Vida_Payment_Gateway' ) . '?order_id=' . $order_id . '&_wpnonce=' . $custom_nonce;

			if ( $the_order_id === $order_id && $the_order_key === $order_key ) {
				// $payment_args['email']        = $email;
				$payment_args['items']         = $complete_order_items;
				$payment_args['amount']        = $amount;
				$payment_args['tx_ref']        = $txnref;
				$payment_args['currency']      = $currency;
				$payment_args['client_id']     = $this->go_live === true ? $this->live_client_id: $this->test_client_id;
				$payment_args['client_secret'] = $this->go_live === true ? $this->live_client_secret: $this->test_client_secret;
				$payment_args['redirect_uri']  = $redirect_url;
				$payment_args['phone_number']  = $order->get_billing_phone();
				$payment_args['environment']   = $this->go_live === true ? 'production': 'sandbox';
				// $payment_args['first_name']   = $order->get_billing_first_name();
				// $payment_args['last_name']    = $order->get_billing_last_name();
				// $payment_args['consumer_id']  = $order->get_customer_id();
				// $payment_args['ip_address']   = $order->get_customer_ip_address();
				// $payment_args['title']        = esc_html__( 'Order Payment', 'vidaveend' );
				// $payment_args['description']  = 'Payment for Order: ' . $order_id;
				// $payment_args['logo']         = wp_get_attachment_url( get_theme_mod( 'custom_logo' ) );
				$payment_args['checkout_url'] = wc_get_checkout_url();
				$payment_args['cancel_url']   = $order->get_cancel_order_url();
			}
			update_post_meta( $order_id, '_vida_txn_ref', $txnref );
		}

		wp_localize_script( 'vida_js', 'vida_args', $payment_args );
	}

	/**
	 * Check Amount Equals.
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @param Float $amount1 1st amount for comparison.
	 * @param Float $amount2  2nd amount for comparison.
	 * @since 2.3.3
	 * @return bool
	 */
	public function amounts_equal( $amount1, $amount2 ): bool {
		return ! ( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > VIDA_EPSILON );
	}


	/**
	 * Verify payment made on the checkout page
	 *
	 * @return void
	 */
	public function vida_verify_payment() {
		$public_key = $this->public_key;
		$secret_key = $this->secret_key;
		$logger     = $this->logger;

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) ) ) {
			if ( isset( $_GET['order_id'] ) ) {
				// Handle expired Session.
				$order_id = urldecode( sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) ) ?? sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
				$order_id = intval( $order_id );
				$order    = wc_get_order( $order_id );

				if ( $order instanceof WC_Order ) {
					WC()->session->set( 'refresh_totals', true );
					wc_add_notice( __( 'We were unable to process your order, please try again.', 'vidaveend' ) );
					$admin_note  = esc_html__( 'Attention: Customer session expired. ', 'vidaveend' ) . '<br>';
					$admin_note .= esc_html__( 'Customer should try again. order has status is now pending payment.', 'vidaveend' );
					$order->add_order_note( $admin_note );
					wp_safe_redirect( $order->get_cancel_order_url() );
				}
				die();
			}
		}

		if ( isset( $_POST['reference'] ) || isset( $_GET['reference'] ) ) {
			$txn_ref  = urldecode( sanitize_text_field( wp_unslash( $_GET['reference'] ) ) ) ?? sanitize_text_field( wp_unslash( $_POST['reference'] ) );
			$o        = explode( '_', sanitize_text_field( $txn_ref ) );
			$order_id = intval( $o[1] );
			$order    = wc_get_order( $order_id );
			$sec_key  = $this->get_secret_key();

			// Communicate with Vida to confirm payment.
			$max_attempts = 3;
			$attempt      = 0;
			$success      = false;

			while ( $attempt < $max_attempts && ! $success ) {
				$args = array(
					'method'  => 'GET',
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $sec_key,
					),
				);

				$order->add_order_note( esc_html__( 'verifying the Payment of Vida...', 'vidaveend' ) );

				$response = wp_safe_remote_request( $this->base_url . '/bnplrequests/'.$txn_ref.'/status', $args );

				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					// Request successful.
					$current_response                  = \json_decode( $response['body'] );
					$is_cancelled_or_pending_on_vida = in_array( $current_response->data->status, array( 'cancelled', 'pending' ), true );
					if ( isset( $_GET['status'] ) && 'cancelled' === $_GET['status'] && $is_cancelled_or_pending_on_vida ) {
						if ( $order instanceof WC_Order ) {
							$order->add_order_note( esc_html__( 'The customer clicked on the cancel button on Checkout.', 'vidaveend' ) );
							$order->update_status( 'cancelled' );
							$admin_note = esc_html__( 'Attention: Customer clicked on the cancel button on the payment gateway. We have updated the order to cancelled status. ', 'vidaveend' ) . '<br>';
							$order->add_order_note( $admin_note );
						}
						header( 'Location: ' . wc_get_cart_url() );
						die();
					} else {
						if ( 'pending' === $current_response->data->status ) {

							if ( $order instanceof WC_Order ) {
								$order->add_order_note( esc_html__( 'Payment Attempt Failed. Please Try Again.', 'vidaveend' ) );
								$admin_note = esc_html__( 'Customer Payment Attempt failed. Advise customer to try again with a different Payment Method', 'vidaveend' ) . '<br>';
								if ( count( $current_response->log->history ) !== 0 ) {
									$last_item_in_history = $current_response->log->history[ count( $current_response->log->history ) - 1 ];
									$message              = json_decode( $last_item_in_history->message, true );
									$this->logger->error( 'Failed Customer Attempt Explanation for ' . $txn_ref . ':' . wp_json_encode( $message ) );
									$reason = $message['error']['explanation'] ?? $message['errors'][0]['message'] ?? 'Unknown';
									/* translators: %s: Reason */
									$admin_note .= sprintf( __( 'Reason: %s', 'vidaveend' ), $reason );
								} else {
									$admin_note .= esc_html__( 'Reason: Unknown', 'vidaveend' );
								}

								$order->add_order_note( $admin_note );
							}
							header( 'Location: ' . wc_get_checkout_url() );
							die();
						}

						if ( 'failed' === $current_response->data->status ) {

							if ( $order instanceof WC_Order ) {
								$order->add_order_note( esc_html__( 'Payment Attempt Failed. Try Again', 'vidaveend' ) );
								$order->update_status( 'failed' );
								$admin_note = esc_html__( 'Payment Failed ', 'vidaveend' ) . '<br>';
								if ( count( $current_response->log->history ) !== 0 ) {
									$last_item_in_history = $current_response->log->history[ count( $current_response->log->history ) - 1 ];
									$message              = json_decode( $last_item_in_history->message, true );
									$this->logger->error( 'Failed Customer Attempt Explanation for ' . $txn_ref . ':' . wp_json_encode( $message ) );
									$reason = $message['error']['explanation'] ?? $message['errors'][0]['message'] ?? 'Non-Given';
									/* translators: %s: Reason */
									$admin_note .= sprintf( __( 'Reason: %s', 'vidaveend' ), $reason );

								} else {
									$admin_note .= esc_html__( 'Reason: Non-Given', 'vidaveend' );
								}
								$order->add_order_note( $admin_note );
							}
							header( 'Location: ' . wc_get_checkout_url() );
							die();
						}

						$success = true;
					}
				} else {
					// Retry.
					++$attempt;
					usleep( 2000000 ); // Wait for 2 seconds before retrying (adjust as needed).
				}
			}

			if ( ! $success ) {
				// Get the transaction from your DB using the transaction reference (txref)
				// Queue it for requery. Preferably using a queue system. The requery should be about 15 minutes after.
				// Ask the customer to contact your support and you should escalate this issue to the Vida support team. Send this as an email and as a notification on the page. just incase the page timesout or disconnects.
				$order->add_order_note( esc_html__( 'The payment didn\'t return a valid response. It could have timed out or abandoned by the customer on Vida', 'vidaveend' ) );
				$order->update_status( 'on-hold' );
				$customer_note  = 'Thank you for your order.<br>';
				$customer_note .= 'We had an issue confirming your payment, but we have put your order <strong>on-hold</strong>. ';
				$customer_note .= esc_html__( 'Please, contact us for information regarding this order.', 'vidaveend' );
				$admin_note     = esc_html__( 'Attention: New order has been placed on hold because we could not get a definite response from the payment gateway. Kindly contact the Vida support team at developers@vidaveend.com to confirm the payment.', 'vidaveend' ) . ' <br>';
				$admin_note    .= esc_html__( 'Payment Reference: ', 'vidaveend' ) . $txn_ref;

				$order->add_order_note( $customer_note, 1 );
				$order->add_order_note( $admin_note );

				wc_add_notice( $customer_note, 'notice' );
				$this->logger->error( 'Failed to verify transaction ' . $txn_ref . ' after multiple attempts.' );
			} else {
				// Transaction verified successfully.
				// Proceed with setting the payment on hold.
				$response = json_decode( $response['body'] );
				$this->logger->info( wp_json_encode( $response ) );
				if ( (bool) $response->data->status ) {
					$amount = (float) $response->data->requested_amount;
					if ( $response->data->currency !== $order->get_currency() || ! $this->amounts_equal( $amount, $order->get_total() ) ) {
						$order->update_status( 'on-hold' );
						$customer_note  = 'Thank you for your order.<br>';
						$customer_note .= 'Your payment successfully went through, but we have to put your order <strong>on-hold</strong> ';
						$customer_note .= 'because the we couldn\t verify your order. Please, contact us for information regarding this order.';
						$admin_note     = esc_html__( 'Attention: New order has been placed on hold because of incorrect payment amount or currency. Please, look into it.', 'vidaveend' ) . '<br>';
						$admin_note    .= esc_html__( 'Amount paid: ', 'vidaveend' ) . $response->data->currency . ' ' . $amount . ' <br>' . esc_html__( 'Order amount: ', 'vidaveend' ) . $order->get_currency() . ' ' . $order->get_total() . ' <br>' . esc_html__( ' Reference: ', 'vidaveend' ) . $response->data->reference;
						$order->add_order_note( $customer_note, 1 );
						$order->add_order_note( $admin_note );
					} else {
						$order->payment_complete( $order->get_id() );
						if ( 'yes' === $this->auto_complete_order ) {
							$order->update_status( 'completed' );
						}
						$order->add_order_note( 'Payment was successful on Vida' );
						$order->add_order_note( 'Vida  reference: ' . $txn_ref );

						$customer_note  = 'Thank you for your order.<br>';
						$customer_note .= 'Your payment was successful, we are now <strong>processing</strong> your order.';
						$order->add_order_note( $customer_note, 1 );
					}
				}
			}
			wc_add_notice( $customer_note, 'notice' );
			WC()->cart->empty_cart();

			$redirect_url = $this->get_return_url( $order );
			header( 'Location: ' . $redirect_url );
			die();
		}

		wp_safe_redirect( home_url() );
		die();
	}

	/**
	 * Get the Ip of the current request.
	 *
	 * @return string
	 */
	public function vida_get_client_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				foreach ( $ip_list as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}

		return 'UNKNOWN';
	}

	/**
	 * Process Webhook notifications.
	 */
	public function vida_notification_handler() {
		$public_key = $this->public_key;
		$secret_key = $this->secret_key;
		$logger     = $this->logger;
		$sdk        = $this->sdk;

		$merchant_secret_hash = hash_hmac( 'SHA512', $public_key, $secret_key );

		if ( VIDA_ALLOWED_WEBHOOK_IP_ADDRESS !== $this->vida_get_client_ip() ) {
			$this->logger->info( 'Faudulent Webhook Notification Attempt [Access Restricted]: ' . (string) $this->vida_get_client_ip() );
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => 'Unauthorized Access (Restriction)',
				),
				WP_Http::UNAUTHORIZED
			);
		}

		$event = file_get_contents( 'php://input' );

		http_response_code( 200 );
		$event = json_decode( $event );

		if ( empty( $event->notify ) && empty( $event->data ) ) {
			$this->logger->info( 'Webhook: ' . wp_json_encode( $event ) );
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => 'Webhook sent is deformed. missing data object.',
				),
				WP_Http::BAD_REQUEST
			);
		}

		if ( 'test_assess' === $event->notify ) {
			wp_send_json(
				array(
					'status'  => 'success',
					'message' => 'Webhook Test Successful. handler is accessible',
				),
				WP_Http::OK
			);
		}

		$this->logger->info( 'Webhook: ' . wp_json_encode( $event ) );

		if ( 'transaction' === $event->notify ) {
			sleep( 2 );
			// phpcs:ignore.
			$event_type = $event->notifyType;
			$event_data = $event->data;

			// check if transaction reference starts with WOO on hpos enabled.
			if ( substr( $event_data->reference, 0, 4 ) !== 'WOO_' ) {
				wp_send_json(
					array(
						'status'  => 'failed',
						'message' => 'The transaction reference ' . $event_data->reference . ' is not a Vida WooCommerce Generated transaction',
					),
					WP_Http::BAD_REQUEST
				);
			}

			$txn_ref  = sanitize_text_field( $event_data->reference );
			$o        = explode( '_', $txn_ref );
			$order_id = intval( $o[1] );
			$order    = wc_get_order( $order_id );

			// get order status.
			if ( ! $order ) {
				wp_send_json(
					array(
						'status'  => 'failed',
						'message' => 'This transaction does not exist.',
					),
					WP_Http::BAD_REQUEST
				);
			}

			$current_order_status = $order->get_status();

			/**
			 * Fires after the webhook has been processed.
			 *
			 * @param string $event The webhook event.
			 * @since 1.0.0
			 */
			do_action( 'vida_webhook_after_action', wp_json_encode( $event, true ) );
			// TODO: Handle Checkout Blocks draft status for WooCommerce Blocks users.
			$statuses_in_question = array( 'pending', 'on-hold', 'cancelled' );
			if ( 'failed' === $current_order_status ) {
				// NOTE: customer must have tried to make payment again in the same session.
				$statuses_in_question[] = 'failed';
			}

			if ( ! in_array( $current_order_status, $statuses_in_question, true ) ) {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => 'Order already processed',
					),
					WP_Http::CREATED
				);
			}

			// Verify transaction and give value.
			// Communicate with Vida to confirm payment.
			$max_attempts = 3;
			$attempt      = 0;
			$success      = false;

			while ( $attempt < $max_attempts && ! $success ) {
				$args = array(
					'method'  => 'GET',
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $secret_key,
					),
				);

				$order->add_order_note( esc_html__( 'verifying the Payment on Vida...', 'vidaveend' ) );

				$response = wp_safe_remote_request( $this->base_url . 'transaction/verify/:' . $txn_ref, $args );

				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					// Request successful.
					$current_response                  = \json_decode( $response['body'] );
					$is_cancelled_or_pending_on_vida = in_array( $current_response->data->status, array( 'cancelled', 'pending' ), true );
					if ( isset( $_GET['status'] ) && 'cancelled' === $_GET['status'] && $is_cancelled_or_pending_on_vida ) { // phpcs:ignore
						if ( $order instanceof WC_Order ) {
							$order->add_order_note( esc_html__( 'The customer clicked on the cancel button on Checkout.', 'vidaveend' ) );
							$order->update_status( 'cancelled' );
							$admin_note = esc_html__( 'Attention: Customer clicked on the cancel button on the payment gateway. We have updated the order to cancelled status. ', 'vidaveend' ) . '<br>';
							$order->add_order_note( $admin_note );
						}
					} else {
						if ( 'pending' === $current_response->data->status ) {

							if ( $order instanceof WC_Order ) {
								$order->add_order_note( esc_html__( 'Payment Attempt Failed. Please Try Again.', 'vidaveend' ) );
								$admin_note = esc_html__( 'Customer Payment Attempt failed. Advise customer to try again with a different Payment Method', 'vidaveend' ) . '<br>';
								if ( count( $current_response->log->history ) !== 0 ) {
									$last_item_in_history = $current_response->log->history[ count( $current_response->log->history ) - 1 ];
									$message              = json_decode( $last_item_in_history->message, true );
									$this->logger->error( 'Failed Customer Attempt Explanation for ' . $txn_ref . ':' . wp_json_encode( $message ) );
									$reason = $message['error']['explanation'] ?? $message['errors'][0]['message'] ?? 'Unknown';
									/* translators: %s: Reason */
									$admin_note .= sprintf( __( 'Reason: %s', 'vidaveend' ), $reason );
								} else {
									$admin_note .= esc_html__( 'Reason: Unknown', 'vidaveend' );
								}

								$order->add_order_note( $admin_note );
							}
						}

						if ( 'failed' === $current_response->data->status ) {

							if ( $order instanceof WC_Order ) {
								$order->add_order_note( esc_html__( 'Payment Attempt Failed. Try Again', 'vidaveend' ) );
								$order->update_status( 'failed' );
								$admin_note = esc_html__( 'Payment Failed ', 'vidaveend' ) . '<br>';
								if ( count( $current_response->log->history ) !== 0 ) {
									$last_item_in_history = $current_response->log->history[ count( $current_response->log->history ) - 1 ];
									$message              = json_decode( $last_item_in_history->message, true );
									$this->logger->error( 'Failed Customer Attempt Explanation for ' . $txn_ref . ':' . wp_json_encode( $message ) );
									$reason = $message['error']['explanation'] ?? $message['errors'][0]['message'] ?? 'Non-Given';
									/* translators: %s: Reason */
									$admin_note .= sprintf( __( 'Reason: %s', 'vidaveend' ), $reason );

								} else {
									$admin_note .= esc_html__( 'Reason: Non-Given', 'vidaveend' );
								}
								$order->add_order_note( $admin_note );
							}
						}

						$success = true;
					}
				} else {
					// Retry.
					++$attempt;
					usleep( 2000000 ); // Wait for 2 seconds before retrying (adjust as needed).
				}
			}

			if ( ! $success ) {
				// Get the transaction from your DB using the transaction reference (txref)
				// Queue it for requery. Preferably using a queue system. The requery should be about 15 minutes after.
				// Ask the customer to contact your support and you should escalate this issue to the Vida support team. Send this as an email and as a notification on the page. just incase the page timesout or disconnects.
				$order->add_order_note( esc_html__( 'The payment didn\'t return a valid response. It could have timed out or abandoned by the customer on Vida', 'vidaveend' ) );
				$order->update_status( 'on-hold' );
				$admin_note  = esc_html__( 'Attention: New order has been placed on hold because we could not get a definite response from the payment gateway. Kindly contact the Vida support team at developers@vidaveend.com to confirm the payment.', 'vidaveend' ) . ' <br>';
				$admin_note .= esc_html__( 'Payment Reference: ', 'vidaveend' ) . $txn_ref;
				$order->add_order_note( $admin_note );
				$this->logger->error( 'Failed to verify transaction ' . $txn_ref . ' after multiple attempts.' );
			} else {
				// Transaction verified successfully.
				// Proceed with setting the payment on hold.
				$response = json_decode( $response['body'] );
				$this->logger->info( wp_json_encode( $response ) );
				if ( (bool) $response->data->status ) {
					$amount = (float) $response->data->requested_amount;
					if ( $response->data->currency !== $order->get_currency() || ! $this->amounts_equal( $amount, $order->get_total() ) ) {
						$order->update_status( 'on-hold' );
						$admin_note  = esc_html__( 'Attention: New order has been placed on hold because of incorrect payment amount or currency. Please, look into it.', 'vidaveend' ) . '<br>';
						$admin_note .= esc_html__( 'Amount paid: ', 'vidaveend' ) . $response->data->currency . ' ' . $amount . ' <br>' . esc_html__( 'Order amount: ', 'vidaveend' ) . $order->get_currency() . ' ' . $order->get_total() . ' <br>' . esc_html__( ' Reference: ', 'vidaveend' ) . $response->data->reference;
						$order->add_order_note( $admin_note );
					} else {
						$order->payment_complete( $order->get_id() );
						if ( 'yes' === $this->auto_complete_order ) {
							$order->update_status( 'completed' );
						}
						$order->add_order_note( 'Payment was successful on Vida' );
						$order->add_order_note( 'Vida  reference: ' . $txn_ref );

						$customer_note  = 'Thank you for your order.<br>';
						$customer_note .= 'Your payment was successful, we are now <strong>processing</strong> your order.';
						$order->add_order_note( $customer_note, 1 );
					}
				}
			}

			wp_send_json(
				array(
					'status'  => 'success',
					'message' => 'Order Processed Successfully',
				),
				WP_Http::CREATED
			);
		}

		wp_send_json(
			array(
				'status'  => 'failed',
				'message' => 'Unable to Processed Successfully',
			),
			WP_Http::CREATED
		);
		exit();
	}
}