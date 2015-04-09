<?php
/*
Plugin Name: CardConnect Payment Gateway
Plugin URI: http://sofcorp.com/
Description: Accept credit card payments in your WooCommerce store!
Version: 0.1.0
Author: SOF Inc <eran@sofcorp.com>
Author URI: http://sofcorp.com

	Copyright: © 2015 SOF Inc <eran@sofcorp.com>.
	License: GNU General Public License v2
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if(!defined('ABSPATH')){
	exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'CardConnectPaymentGateway_init', 0);
function CardConnectPaymentGateway_init(){

	if(class_exists('CardConnectPaymentGateway') || !class_exists('WC_Payment_Gateway')){
		return;
	}

	/**
	 * Gateway class
	 */
	class CardConnectPaymentGateway extends WC_Payment_Gateway {

		private $cc_client = null;
		private $api_credentials;
		private $cc_url = array(
			'sandbox' => 'https://fts.cardconnect.com:6443/cardconnect/rest',
			'production' => 'https://fts.cardconnect.com:8443/cardconnect/rest'
		);
		private $mode;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct(){
			$this->id = 'card_connect';
			$this->icon = apply_filters('woocommerce_CardConnectPaymentGateway_icon', '');
			$this->has_fields = true;
			$this->method_title = __('CardConnect Payment Gateway', 'cardconnect-payment-gateway');
			$this->method_description = __('Payment gateway for CardConnect Payment Processing', 'cardconnect-payment-gateway');
			$this->supports = array(
				'default_credit_card_form',
				'refunds',
				'products',
				// 'subscriptions',
				// 'subscription_cancellation',
				// 'subscription_reactivation',
				// 'subscription_suspension',
				// 'subscription_amount_changes',
				// 'subscription_payment_method_change',
				// 'subscription_date_changes'
			);
			$this->view_transaction_url = '#';

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Load user options
			$this->load_options();

			// Actions
			add_action('wp_enqueue_scripts', array( $this, 'register_scripts'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_CardConnectPaymentGateway', array($this, 'thankyou_page'));
		}

		/**
		 * Load SDK for communicating with CardConnect servers
		 *
		 * @return void
		 */
		protected function get_cc_client(){
			if(is_null($this->cc_client)){
				require_once 'rest-client/CardConnectRestClient.php';
				$this->cc_client = new CardConnectRestClient(
					$this->api_credentials['url'],
					$this->api_credentials['user'],
					$this->api_credentials['pass']
				);
			}
			return $this->cc_client;
		}

		/**
		 * Load user options into class
		 *
		 * @return void
		 */
		protected function load_options(){

			$this->enabled = $this->get_option('enabled');

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->mode = $this->get_option('mode', 'capture');

			$this->sandbox = $this->get_option('sandbox');

			$env_key = $this->sandbox == 'no' ? 'production' : 'sandbox';
			$this->api_credentials = array(
				'url' => $this->cc_url[$env_key],
				'mid' => $this->get_option("{$env_key}_mid"),
				'user' => $this->get_option("{$env_key}_user"),
				'pass' => $this->get_option("{$env_key}_password"),
			);
		}

		/**
		 * Create form fields for the payment gateway
		 *
		 * @return void
		 */
		public function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'label' => __('Enable CardConnect Payments', 'woocommerce'),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => __('Credit card', 'woocommerce'),
					'desc_tip' => true
				),
				'description' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
					'default' => 'Payment secured by CardConnect.',
					'desc_tip' => true
				),
				'mode' => array(
					'title' => __('Payment Mode', 'woocommerce'),
					'label' => __('Capture payment or only authorize it.', 'woocommerce'),
					'type' => 'select',
					'description' => __('Select <strong>Authorize Only</strong> if you prefer to manually approve each transaction in the CardConnect dashboard.', 'woocommerce'),
					'default' => 'capture',
					'options' => array(
						'capture' => __('Capture Payment', 'woocommerce'),
						'auth_only' => __('Authorize Only', 'woocommerce')
					)
				),
				'sandbox' => array(
					'title' => __('Sandbox', 'woocommerce'),
					'label' => __('Enable Sandbox Mode', 'woocommerce'),
					'type' => 'checkbox',
					'description' => __('Place the payment gateway in sandbox mode using the sandbox authentication fields below (real payments will not be taken).', 'woocommerce'),
					'default' => 'yes',
					'desc_tip' => true
				),
				'sandbox_mid' => array(
					'title' => __('Sandbox Merchant ID (MID)', 'woocommerce'),
					'type' => 'text',
					'description' => __('Platform identifier required to be included in API calls from your ERP application to the CardConnect demo system.  Once your interface is live, you will be assigned a unique id from the processor.  During development and testing, you can use this test merchant id.', 'woocommerce'),
					'default' => '496160873888',
					'desc_tip' => true
				),
				'sandbox_user' => array(
					'title' => __('Sandbox Username', 'woocommerce'),
					'type' => 'text',
					'description' => __('This is the default information, you may use this or an alternative if provided by CardConnect', 'woocommerce'),
					'default' => 'testing',
					'desc_tip' => true
				),
				'sandbox_password' => array(
					'title' => __('Sandbox Password', 'woocommerce'),
					'type' => 'text',
					'description' => __('This is the default information, you may use this or an alternative if provided by CardConnect', 'woocommerce'),
					'default' => 'testing123',
					'desc_tip' => true
				),
				'production_mid' => array(
					'title' => __('Merchant ID (MID)', 'woocommerce'),
					'type' => 'text',
					'description' => __('Your unique MID from CardConnect.', 'woocommerce'),
					'default' => '',
					'desc_tip' => true
				),
				'production_user' => array(
					'title' => __('Username', 'woocommerce'),
					'type' => 'text',
					'description' => __('Enter the credentials obtained from CardConnect', 'woocommerce'),
					'default' => '',
					'desc_tip' => true
				),
				'production_password' => array(
					'title' => __('Password', 'woocommerce'),
					'type' => 'text',
					'description' => __('Enter the credentials obtained from CardConnect', 'woocommerce'),
					'default' => '',
					'desc_tip' => true
				),
			);
		}

		/**
		 * Process the order payment status
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment($order_id){
			global $woocommerce;
			$order = new WC_Order($order_id);

			$token = isset( $_POST['card_connect_token'] ) ? wc_clean( $_POST['card_connect_token'] ) : false;

			if(!$token){
				wc_add_notice(__('Payment error: ', 'woothemes') . 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'error');
				return;
			}

			$request = array(
				'merchid'   => $this->api_credentials['mid'],
				'account'   => $token,
				'expiry'    => preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry'])),
				'cvv2'      => wc_clean($_POST['card_connect-card-cvc']),
				'amount'    => $woocommerce->cart->total * 100,
				'currency'  => "USD",
				'orderid'   => $order->id,
				'name'      => trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
				'street'    => $order->billing_address_1,
				'city'      => $order->billing_city,
				'region'    => $order->billing_state,
				'country'   => $order->billing_country,
				'postal'    => $order->billing_postcode,
				'capture'   => $this->mode === 'capture' ? 'Y' : 'N',
			);

			$response = $this->get_cc_client()->authorizeTransaction($request);

			if('A' === $response['respstat']){

				$order->payment_complete();

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				$woocommerce->cart->empty_cart();

				$order->add_order_note(sprintf(__( 'CardConnect payment approved (ID: %s, Authcode: %s)', 'woocommerce'), $response['retref'], $response['authcode']));

				// Return thankyou redirect
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order)
				);

			}else if('C' === $response['respstat']){
				wc_add_notice(__('Payment error: ', 'woothemes') . 'Order Declined : ' . $response['resptext'], 'error');
				$order->add_order_note(sprintf(__( 'CardConnect declined transaction. Response: %s', 'woocommerce'), $response['resptext']));
			}else{
				wc_add_notice(__('Payment error: ', 'woothemes') . 'An error prevented this transaction from completing. Please confirm your information and try again.', 'error');
				$order->add_order_note(sprintf(__( 'CardConnect failed transaction. Response: %s', 'woocommerce'), $response['resptext']));
			}

			$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
			return;

		}

		/**
		 * Output for the order received page.
		 *
		 * @return void
		 */
		public function thankyou(){
			if($description = $this->get_description()){
				echo wpautop(wptexturize(wp_kses_post($description)));
			}

			echo '<h2>' . __('Our Details', 'cardconnect-payment-gateway') . '</h2>';

			echo '<ul class="order_details CardConnectPaymentGateway_details">';

			$fields = apply_filters('woocommerce_CardConnectPaymentGateway_fields', array(
				'example_field' => __('Example field', 'cardconnect-payment-gateway')
			));

			foreach($fields as $key => $value){
				if(!empty($this->$key)){
					echo '<li class="' . esc_attr($key) . '">' . esc_attr($value) . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
				}
			}

			echo '</ul>';
		}

		/**
		 * Output for the order received page.
		 *
		 * @return void
		 */
		public function payment_fields(){
			wp_enqueue_script('woocommerce-cardconnect');
			wp_localize_script('woocommerce-cardconnect', 'wooCardConnect',
				array(
					'isLive' => $this->sandbox === 'no' ? true : false
				)
			);

			$fields = array(
				'card-number-field' => '<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '-card-number">' . __( 'Card Number', 'woocommerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" />
				</p>',
				'card-expiry-field' => '<p class="form-row form-row-first">
					<label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . __( 'MM / YY', 'woocommerce' ) . '" name="' . $this->id . '-card-expiry" />
				</p>',
				'card-cvc-field' => '<p class="form-row form-row-last">
					<label for="' . esc_attr( $this->id ) . '-card-cvc">' . __( 'Card Code', 'woocommerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . __( 'CVC', 'woocommerce' ) . '" name="' . $this->id . '-card-cvc" />
				</p>'
			);


			$this->credit_card_form(null, $fields);
		}

		/**
		 * Register Frontend Assets
		 **/
		public function register_scripts(){
			wp_register_script(
				'woocommerce-cardconnect',
				plugins_url('javascript/dist/woocommerce-cc-gateway.js', __FILE__),
				array('jquery'), '1.0', true
			);
		}

	}

	/**
	 * Add the Gateway to WooCommerce
	 **/
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_CardConnectPaymentGateway');
	function woocommerce_add_gateway_CardConnectPaymentGateway($methods){
		$methods[] = 'CardConnectPaymentGateway';

		return $methods;
	}

}
