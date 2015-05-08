<?php
/*
Plugin Name: CardConnect Payment Gateway
Plugin URI: http://sofcorp.com/
Description: Accept credit card payments in your WooCommerce store!
Version: 0.5.0
Author: SOF Inc <eran@sofcorp.com>
Author URI: http://sofcorp.com

	Copyright: © 2015 SOF Inc <eran@sofcorp.com>.
	License: GNU General Public License v2
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if(!defined('ABSPATH')){
	exit; // Exit if accessed directly
}

define('WC_CARDCONNECT_VER', '0.5.0');
define('WC_CARDCONNECT_PLUGIN_PATH', untrailingslashit(plugin_basename(__DIR__)));
define('WC_CARDCONNECT_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');

add_action('plugins_loaded', 'CardConnectPaymentGateway_init', 0);
function CardConnectPaymentGateway_init(){

	if(class_exists('CardConnectPaymentGateway') || !class_exists('WC_Payment_Gateway')){
		return;
	}

	/**
	 * Gateway class
	 */
	class CardConnectPaymentGateway extends WC_Payment_Gateway {

		private $domain = 'cardconnect.com';
		private $rest_path = '/cardconnect/rest';
		private $cs_path = '/cardsecure/cs';
		private $cc_ports = array(
			'sandbox' => '6443',
			'production' => '8443'
		);

		private $env_key;
		private $cc_client = null;
		private $api_credentials;
		private $mode;
		private $site;
		private $card_types = array();
		private $verification;
		private $registration_enabled;
		private $profiles_enabled;
		private $saved_cards;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct(){
			$this->id = 'card_connect';
			$this->icon = apply_filters('woocommerce_CardConnectPaymentGateway_icon', '');
			$this->has_fields = true;
			$this->method_title = __('CardConnect', 'cardconnect-payment-gateway');
			$this->method_description = __('Payment gateway for CardConnect', 'cardconnect-payment-gateway');
			$this->supports = array(
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

			// Load user options
			$this->load_options();

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Actions
			add_action('wp_enqueue_scripts', array( $this, 'register_scripts'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_CardConnectPaymentGateway', array($this, 'thankyou_page'));

			// Append local includes dir to include path
			set_include_path(get_include_path() . PATH_SEPARATOR . plugin_dir_path(__FILE__) . 'includes');

			if($this->profiles_enabled){
				require_once 'CardConnectSavedCards.php';
				$this->saved_cards = new CardConnectSavedCards($this->get_cc_client(), $this->api_credentials['mid']);
			}

		}

		/**
		 * Load SDK for communicating with CardConnect servers
		 *
		 * @return void
		 */
		protected function get_cc_client(){
			if(is_null($this->cc_client)){
				require_once 'CardConnectRestClient.php';
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

			$this->registration_enabled = WC_Admin_Settings::get_option('woocommerce_enable_signup_and_login_from_checkout') === 'yes' ? true : false;
			$this->profiles_enabled = $this->registration_enabled && $this->get_option('enable_profiles') === 'yes';

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->card_types = $this->get_option('card_types');
			$this->mode = $this->get_option('mode', 'capture');

			$this->sandbox = $this->get_option('sandbox');
			$this->site = $this->get_option('site');

			$this->env_key = $this->sandbox == 'no' ? 'production' : 'sandbox';
			$port = $this->cc_ports[$this->env_key];

			$this->api_credentials = array(
				'url' => "https://{$this->site}.{$this->domain}:{$port}{$this->rest_path}",
				'mid' => $this->get_option("{$this->env_key}_mid"),
				'user' => $this->get_option("{$this->env_key}_user"),
				'pass' => $this->get_option("{$this->env_key}_password"),
			);

			$this->verification = array(
				'void_cvv' => $this->get_option('void_cvv'),
				'void_avs' => $this->get_option('void_avs'),
			);
		}

		/**
		 * Create form fields for the payment gateway
		 *
		 * @return void
		 */
		public function init_form_fields(){

			$profile_tooltip = array();
			$profile_tooltip['reg_enabled'] = __('Store payment information on CardConnect\'s servers as a convenience to customers.', 'woocommerce');
			$profile_tooltip['reg_disabled'] = __('You must enable registration on checkout in order to offer this feature.', 'woocommerce');

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
					'description' => __('This controls the title that the user sees during checkout.', 'woocommerce'),
					'default' => __('Credit card', 'woocommerce'),
					'desc_tip' => true
				),
				'description' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the description that the user sees during checkout.', 'woocommerce'),
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
					'description' => __('This is the default information. You may use this or an alternative if provided by CardConnect.', 'woocommerce'),
					'default' => '',
					'class' => 'sandbox_input',
					'desc_tip' => true
				),
				'sandbox_user' => array(
					'title' => __('Sandbox Username', 'woocommerce'),
					'type' => 'text',
					'description' => __('This is the default information. You may use this or an alternative if provided by CardConnect.', 'woocommerce'),
					'default' => '',
					'class' => 'sandbox_input',
					'desc_tip' => true
				),
				'sandbox_password' => array(
					'title' => __('Sandbox Password', 'woocommerce'),
					'type' => 'password',
					'description' => __('This is the default information. You may use this or an alternative if provided by CardConnect.', 'woocommerce'),
					'default' => '',
					'class' => 'sandbox_input',
					'desc_tip' => true
				),
				'production_mid' => array(
					'title' => __('Live Merchant ID (MID)', 'woocommerce'),
					'type' => 'text',
					'description' => __('Your unique MID from CardConnect.', 'woocommerce'),
					'default' => '',
					'class' => 'production_input',
					'desc_tip' => true
				),
				'production_user' => array(
					'title' => __('Live Username', 'woocommerce'),
					'type' => 'text',
					'description' => __('Enter the credentials obtained from CardConnect', 'woocommerce'),
					'default' => '',
					'class' => 'production_input',
					'desc_tip' => true
				),
				'production_password' => array(
					'title' => __('Live Password', 'woocommerce'),
					'type' => 'password',
					'description' => __('Enter the credentials obtained from CardConnect', 'woocommerce'),
					'default' => '',
					'class' => 'production_input',
					'desc_tip' => true
				),
				'site' => array(
					'title' => __('Site', 'woocommerce'),
					'type' => 'text',
					'description' => __('Enter the site provided to you upon opening your CardConnect merchant account', 'woocommerce'),
				),
				'card_types' => array(
					'title' => __('Card Types', 'woocommerce'),
					'type' => 'multiselect',
					'class' => 'wc-enhanced-select',
					'default' => array('visa', 'mastercard', 'discover', 'amex'),
					'description' => __('Select the card types to be allowed for transactions. <strong>This must match your Merchant Agreement.</strong>', 'woocommerce'),
					'desc_tip' => false,
					'options' => array(
						'visa' => __('Visa', 'woocommerce'),
						'mastercard' => __('Mastercard', 'woocommerce'),
						'discover' => __('Discover', 'woocommerce'),
						'amex' => __('American Express', 'woocommerce')
					),
				),
				'enable_profiles' => array(
					'title' => __('Saved Cards', 'woocommerce'),
					'label' => __('Allow customers to save payment information', 'woocommerce'),
					'type' => 'checkbox',
					'description' => $this->registration_enabled ? $profile_tooltip['reg_enabled'] : $profile_tooltip['reg_disabled'],
					'default' => 'no',
					'desc_tip' => true,
					'disabled' => !$this->registration_enabled,
				),
				'void_avs' => array(
					'title' => __('Void on AVS failure', 'woocommerce'),
					'label' => __('Active', 'woocommerce'),
					'type' => 'checkbox',
					'description' => __('Void order if <strong>Address and ZIP</strong> do not match.', 'woocommerce'),
					'default' => 'yes',
				),
				'void_cvv' => array(
					'title' => __('Void on CVV failure', 'woocommerce'),
					'label' => __('Active', 'woocommerce'),
					'type' => 'checkbox',
					'description' => __('Void order if <strong>CVV2/CVC2/CID</strong> does not match.', 'woocommerce'),
					'default' => 'yes',
				),
			);
		}

		/**
		 * Admin Panel Options
		 * Include CardConnect logo and add some JS for revealing inputs for sandbox vs production
		 *
		 * @access public
		 * @return void
		 */
		public function admin_options(){
			?>

			<img style="margin:20px 0 5px 10px" width="200" height="29" src="<?php echo plugins_url('assets/cardconnect-logo.png', __FILE__) ?>" />

			<?php if(empty($this->api_credentials['mid'])): ?>
				<div class="card-connect-banner updated">
					<p class="main"><h3 style="margin:0;"><?php _e( 'Getting started', 'woocommerce' ); ?></h3></p>
					<p><?php _e( 'CardConnect is a leading provider of payment processing and technology services that helps more than 50,000 merchants across the U.S. accept billions of dollars in card transactions each year. This Extension from CardConnect helps you accept simple, integrated and secure payments on your Woo Commerce store. Please call 877-828-0720 today to get a Merchant Account!', 'woocommerce' ); ?></p>
					<p><a href="http://www.cardconnect.com/" target="_blank" class="button button-primary"><?php _e( 'Visit CardConnect', 'woocommerce' ); ?></a> </p>
				</div>
			<?php endif; ?>

			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
				<script type="text/javascript">
					jQuery('#woocommerce_card_connect_sandbox').on('change', function() {
						var sandbox = jQuery('.sandbox_input').closest('tr');
						var production = jQuery('.production_input').closest( 'tr' );
						if(jQuery(this).is(':checked')){
							sandbox.show();
							production.hide();
						}else{
							sandbox.hide();
							production.show();
						}
					}).change();
					jQuery(function($){
						$('#mainform').submit(function(){
							if(!$('#woocommerce_card_connect_sandbox').is(':checked')){
								var allowSubmit = true;
								$('.production_input').each(function(){
									if($(this).val() == '') allowSubmit = false;
								});
								if(!allowSubmit) alert('Warning! In order to enter Live Mode you must enter a value for MID, Username, and Password.');
								return allowSubmit;
							}
						});
					});
				</script>
			</table>
		<?php
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
			$user_id = get_current_user_id();

			$profile_id = $this->profiles_enabled ? $this->saved_cards->get_user_profile_id($user_id) : false;

			$token = isset( $_POST['card_connect_token'] ) ? wc_clean( $_POST['card_connect_token'] ) : false;
			$card_name = isset( $_POST['card_connect-card-name'] ) ? wc_clean( $_POST['card_connect-card-name'] ) : false;
			$store_new_card = isset($_POST['card_connect-save-card']) ? wc_clean($_POST['card_connect-save-card']) : false;
			$saved_card_id = isset( $_POST['card_connect-cards'] ) ? wc_clean( $_POST['card_connect-cards'] ) : false;
			$card_alias = isset($_POST['card_connect-new-card-alias']) ? wc_clean($_POST['card_connect-new-card-alias']) : false;

			if(!$token && !$saved_card_id){
				wc_add_notice(__('Payment error: ', 'woothemes') . 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'error');
				return;
			}

			$request = array(
				'merchid'   => $this->api_credentials['mid'],
				'cvv2'      => wc_clean($_POST['card_connect-card-cvc']),
				'amount'    => $order->order_total * 100,
				'currency'  => "USD",
				'orderid'   => sprintf(__('%s - Order #%s', 'woocommerce'), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
				'name'      => $card_name ? $card_name : trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
				'street'    => $order->billing_address_1,
				'city'      => $order->billing_city,
				'region'    => $order->billing_state,
				'country'   => $order->billing_country,
				'postal'    => $order->billing_postcode,
				'capture'   => $this->mode === 'capture' ? 'Y' : 'N',
			);

			if($saved_card_id){

				// Payment is using a stored card, no token or account number to pass
				$request['profile'] = "$profile_id/$saved_card_id";

			}else{

				// Either a basic purchase or adding a new card. Either way, include the expiration date
				$request['expiry'] = preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry']));

				// Adding an additional card to an existing profile -- This requires a separate API call, handled in `add_account_to_profile`
				if($store_new_card && $profile_id){

					$request['profile'] = $profile_id;

					// The `token` key isn't used by the Auth/Capture service however it's ignored if it's passed as `account` when updating profiles
					$request['token'] = $token;

					// Get the new card's account id, remove the token key
					$new_account_id = $this->saved_cards->add_account_to_profile($user_id, $card_alias, $request);
					unset($request['token']);

					// Overwrite the profile field with the `profile/acctid` format required by the Auth/Capture service
					$request['profile'] = "$profile_id/$new_account_id";

					// Adding a new card, no existing profile
				}else if($store_new_card && !$profile_id){
					$request['profile'] = 'Y';
					$request['account'] = $token;

					// No request to save card, just pass the token
				}else{
					$request['account'] = $token;
				}

			}

			$response = $this->get_cc_client()->authorizeTransaction($request);

			if('A' === $response['respstat']){

				$order_verification = $this->verify_customer_data($response);
				if(!$order_verification['is_valid']){

					$request = array(
						'merchid' => $this->api_credentials['mid'],
						'currency' => 'USD',
						'retref' => $response['retref'],
					);

					$void_response = $this->get_cc_client()->voidTransaction($request);

					if($void_response['authcode'] === 'REVERS'){
						$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
						foreach($order_verification['errors'] as $error){
							$order->add_order_note(sprintf(__( $error, 'woocommerce')));
							wc_add_notice(__('Payment error: ', 'woothemes') . $error, 'error');
						}
						return;
					}
				}

				$order->payment_complete($response['retref']);
				update_post_meta($order_id, '_transaction_id', $response['retref']);

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				$woocommerce->cart->empty_cart();

				$order->add_order_note(sprintf(__( 'CardConnect payment approved (ID: %s, Authcode: %s)', 'woocommerce'), $response['retref'], $response['authcode']));

				// First time this customer has saved a card, pull the response fields and store in user meta
				if($store_new_card && !$profile_id){
					$this->saved_cards->set_user_profile_id($user_id, $response['profileid']);
					$this->saved_cards->save_user_card($user_id, array($response['acctid'] => $card_alias));
				}

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
		 * Matches response AVS and CVV information against admin preferences
		 *
		 * @return array
		 */
		private function verify_customer_data($response){

			$error = array();

			if($this->verification['void_cvv'] === 'yes' && $response['cvvresp'] === 'N'){
				$error[] = 'Error - Invalid CVV. Please confirm the supplied CVV information.';
			}

			if($this->verification['void_avs'] === 'yes' && $response['avsresp'] === 'N'){
				$error[] = 'Error - Address verification failed. Please confirm the supplied billing information.';
			}

			return array(
				'is_valid' => count($error) === 0,
				'errors' => $error
			);
		}

		/**
		 * Output payment fields and required JS
		 *
		 * @return void
		 */
		public function payment_fields(){

			$isSandbox = $this->sandbox !== 'no';
			$port = $this->cc_ports[$this->env_key];

			wp_enqueue_script( 'wc-credit-card-form' );
			wp_enqueue_style('woocommerce-cardconnect-paymentform');
			wp_enqueue_script('woocommerce-cardconnect');
			wp_localize_script('woocommerce-cardconnect', 'wooCardConnect',
				array(
					'isLive' => !$isSandbox ? true : false,
					'profilesEnabled' => $this->profiles_enabled ? true : false,
					'apiEndpoint' => "https://{$this->site}.{$this->domain}:{$port}{$this->cs_path}",
					'allowedCards' => $this->card_types,
					'userSignedIn' => is_user_logged_in()
				)
			);

			$card_icons = array_reduce($this->card_types, function($carry, $card_name){
				$plugin_path = plugins_url('assets', __FILE__);
				$carry .= "<li class='card-connect-allowed-card__li'><img class='card-connect-allowed-cards__img' src='$plugin_path/$card_name.png' alt='$card_name'/></li>";
				return $carry;
			}, '');

			$template_params = array(
				'card_icons' => $card_icons,
				'is_sandbox' => $isSandbox,
				'profiles_enabled' => $this->profiles_enabled
			);

			if($this->profiles_enabled){
				$template_params['saved_cards'] = $this->saved_cards->get_user_cards(get_current_user_id());
			}

			wc_get_template(
				'card-input.php',
				$template_params,
				WC_CARDCONNECT_PLUGIN_PATH,
				WC_CARDCONNECT_TEMPLATE_PATH
			);
		}

		/**
		 * Process refunds
		 * WooCommerce 2.2 or later
		 *
		 * @param  int $order_id
		 * @param  float $amount
		 * @param  string $reason
		 * @uses   Simplify_ApiException
		 * @uses   Simplify_BadRequestException
		 * @return bool|WP_Error
		 */
		public function process_refund($order_id, $amount = null, $reason = '') {

			$order = new WC_Order($order_id);
			$retref = get_post_meta($order_id, '_transaction_id', true);

			$request = array(
				'merchid' => $this->api_credentials['mid'],
				'amount' => $amount * 100,
				'currency' => 'USD',
				'retref' => $retref,
			);

			$response = $this->get_cc_client()->refundTransaction($request);

			if('A' === $response['respstat']){
				$order->add_order_note(sprintf(__('CardConnect refunded $%s. Response: %s. Retref: %s', 'woocommerce'), $response['amount'], $response['resptext'], $response['retref']));
				return true;
			}else{
				throw new Exception( __( 'Refund was declined.', 'woocommerce' ) );
				return false;
			}

		}

		/**
		 * Register Frontend Assets
		 **/
		public function register_scripts(){
			wp_register_script(
				'woocommerce-cardconnect',
				plugins_url('javascript/dist/woocommerce-cc-gateway.js', __FILE__),
				array('jquery'), WC_CARDCONNECT_VER, true
			);
			wp_register_style(
				'woocommerce-cardconnect-paymentform',
				plugins_url('stylesheets/woocommerce-cc-gateway.css', __FILE__),
				null, WC_CARDCONNECT_VER
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
