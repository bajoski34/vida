/**
 * WooCommerce dependencies
 */
import { getSetting, WC_ } from '@woocommerce/settings';

export const getBlocksConfiguration = () => {
	const vidaServerData = getSetting( 'vida_data', null );

	if ( ! vidaServerData ) {
		throw new Error( 'Vida initialization data is not available' );
	}

	return vidaServerData;
};

/**
 * Creates a payment request using cart data from WooCommerce.
 *
 * @param {Object} Vida - The Vida JS object.
 * @param {Object} cart - The cart data response from the store's AJAX API.
 *
 * @return {Object} A Vida payment request.
 */
export const createPaymentRequestUsingCart = ( vida, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		country: cart.order_data.country_code,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getBlocksConfiguration()?.checkout
			?.needs_payer_phone,
		requestShipping: !!cart.shipping_required,
		displayItems: cart.order_data.displayItems,
	};

	if ( options.country === 'PR' ) {
		options.country = 'NG';
	}

	return vida.paymentRequest( options );
};

/**
 * Updates the given PaymentRequest using the data in the cart object.
 *
 * @param {Object} paymentRequest  The payment request object.
 * @param {Object} cart  The cart data response from the store's AJAX API.
 */
export const updatePaymentRequestUsingCart = ( paymentRequest, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		displayItems: cart.order_data.displayItems,
	};

	paymentRequest.update( options );
};

/**
 * Returns the Vida public key
 *
 * @throws Error
 * @return {string} The public api key for the Vida payment method.
 */
export const getPublicKey = () => {
	const client_id = getBlocksConfiguration()?.public_key;
	if ( ! client_id ) {
		throw new Error(
			'There is no client_id available for Vida. Make sure it is available on the wc.vida_data.public_key property.'
		);
	}
	return public_key;
};