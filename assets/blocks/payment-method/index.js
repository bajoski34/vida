/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constant';
import {
	getBlocksConfiguration,
} from 'wcvidaveend/blocks/utils';

/**
 * Content component
 */
const Content = () => {
	return <div>{ 
        decodeEntities( getBlocksConfiguration()?.description || 
        __('You may be redirected to a secure page to complete your payment.', 'vidaveend') ) }</div>;
};

const VIDA_ASSETS = getBlocksConfiguration()?.asset_url ?? null;


const paymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: (
		<div style={{ display: 'flex', flexDirection: 'row', rowGap: '0em', alignItems: 'center'}}>
			<img
			className='vida-logo-on-checkout'
			src={ `${VIDA_ASSETS}/img/vida.png` }
			alt={ decodeEntities(
				getBlocksConfiguration()?.title || __( 'Vida', 'vidaveend' )
			) }
			/>
		</div>
	),
	placeOrderButtonLabel: __(
		'Proceed to Vida',
		'vidaveend'
	),
	ariaLabel: decodeEntities(
		getBlocksConfiguration()?.title ||
		__( 'Payment via Vida', 'vidaveend' )
	),
	canMakePayment: () => true,
	content: <Content />,
	edit: <Content />,
	paymentMethodId: PAYMENT_METHOD_NAME,
	supports: {
		features:  getBlocksConfiguration()?.supports ?? [],
	},
}

export default paymentMethod;