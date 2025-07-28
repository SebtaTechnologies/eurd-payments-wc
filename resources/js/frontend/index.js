
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

const settings = getSetting( 'eurd_data', {} );

const defaultLabel = __(
	'Pay with EURD',
	'woo-gutenberg-products-block'
);

const defaultDescription = __(
	'No middleman, no fees. Support the seller directly with paying with EURD.',
	'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
const iconUrl = settings.icon;

/**
 * Content component
 */
const Content = () => {
	return decodeEntities( settings.description || defaultDescription );
};

/**
 * EURD payment method config object.
 */
const EURD = {
	name: settings.id,
	label: (
		<div style={{ display: 'inline-flex', alignItems: 'center' }}>
			<span>{label}</span>
			<img
				src={iconUrl}
				alt={label}
				style={{ marginLeft: '8px'}}
			/>
		</div>
	),
	placeOrderButtonLabel: settings.order_btn_txt,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( EURD );
