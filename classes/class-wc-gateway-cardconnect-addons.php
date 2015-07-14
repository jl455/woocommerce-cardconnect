<?php
/**
 * This class enables the gateway to integrate with the Subscriptions extension
 *
 * @since 0.6.0
 */
class CardConnectPaymentGatewayAddons extends CardConnectPaymentGateway{

	/**
	 * Main constructor of class
	 *
	 * @since 0.6.0
	 * @return void
	 */
	public function __construct(){
		parent::__construct();
		if(class_exists('WC_Subscriptions_Order')){
			add_action('scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 3);
			add_filter('woocommerce_subscriptions_renewal_order_meta_query', array($this, 'remove_renewal_order_meta'), 10, 4);
			add_action('woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array($this, 'update_failing_payment_method'), 10, 3);
			// display the current payment method used for a subscription in the "My Subscriptions" table
			add_filter('woocommerce_my_subscriptions_recurring_payment_method', array($this, 'maybe_render_subscription_payment_method'), 10, 3);
		}
	}

	/**
	 * Process the subscription
	 *
	 * Saves the card, if needed, and activates the subscription. This is called when the subscription is first purchased
	 *
	 * @param int $order_id
	 *
	 * @return array
	 *
	 * @since 0.6.0
	 */
	public function process_subscription($order_id){
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
			if($profile_id){

				$request['profile'] = $profile_id;

				// The `token` key isn't used by the Auth/Capture service however it's ignored if it's passed as `account` when updating profiles
				$request['token'] = $token;

				// Get the new card's account id, remove the token key
				$new_account_id = $this->saved_cards->add_account_to_profile($user_id, $card_alias, $request);
				unset($request['token']);

				// Overwrite the profile field with the `profile/acctid` format required by the Auth/Capture service
				$request['profile'] = "$profile_id/$new_account_id";

				// Adding a new card, no existing profile
			}else{
				$request['profile'] = 'Y';
				$request['account'] = $token;
			}

		}

		//Authorizes transaction to be processed
		if ( !is_null( $this->get_cc_client() ) {
			$response = $this->get_cc_client()->authorizeTransaction($request);
		} else {
			wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
			$order->add_order_note( 'CardConnect is not configured!' );
			return;
		}

		// 'A' response is for accepted
		if('A' === $response['respstat']){

			// Need to verify customer data before marking complete
			$order_verification = $this->verify_customer_data($response);
			if(!$order_verification['is_valid']){

				$request = array(
					'merchid' => $this->api_credentials['mid'],
					'currency' => 'USD',
					'retref' => $response['retref'],
				);
			
				if ( !is_null( $this->get_cc_client() ) {
					$void_response = $this->get_cc_client()->voidTransaction($request);
				} else {
					wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
					$order->add_order_note( 'CardConnect is not configured!' );
					return;
				}
				
				if($void_response['authcode'] === 'REVERS'){
					$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
					foreach($order_verification['errors'] as $error){
						$order->add_order_note(sprintf(__( $error, 'woocommerce')));
						wc_add_notice(__('Payment error: ', 'woothemes') . $error, 'error');
					}
					return;
				}
			}

			// Mark order complete and begin completion process
			$order->payment_complete($response['retref']);
			update_post_meta($order_id, '_transaction_id', $response['retref']);

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			$order->add_order_note(sprintf(__( 'CardConnect payment approved (ID: %s, Authcode: %s)', 'woocommerce'), $response['retref'], $response['authcode']));

			// First time this customer has saved a card, pull the response fields and store in user meta
			if(!$saved_card_id && !$profile_id){
				$this->saved_cards->set_user_profile_id($user_id, $response['profileid']);
				$this->saved_cards->save_user_card($user_id, array($response['acctid'] => $card_alias));
			}

			// Activate the subscription
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

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
	 * Process the payment
	 *
	 * If order contains subscriptions, use this class's process_subscription. If not, use the main class's process_payment function
	 *
	 * @param  int $order_id
	 *
	 * @return array
	 *
	 * @since 0.6.0
	 */
	public function process_payment($order_id){
		// Processing subscription
		if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($order_id)){
			return $this->process_subscription($order_id);
		// Processing regular product
		}else{
			return parent::process_payment($order_id);
		}
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order            WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @param $product_id       int The ID of the subscription product for which this payment relates.
	 *
	 * @access public
	 * @return void
	 *
	 * @since 0.6.0
	 */
	public function scheduled_subscription_payment($amount_to_charge, $order, $product_id){

		// Process the payment
		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		// If the process results in error, then marked order as failed. If not, continue subscription
		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}

	/**
	 * process_subscription_payment function
	 *
	 * Retrieve stored card and then process the transaction. If any errors, then return WP Error
	 *
	 * @access public
	 *
	 * @param mixed  $order
	 * @param int    $amount       (default: 0)
	 *
	 * @return true | WP Error
	 *
	 * @since 0.6.0
	 */
	public function process_subscription_payment($order = '', $amount = 0){
		$user_id = $order->user_id;
		$profile_id = $this->profiles_enabled ? $this->saved_cards->get_user_profile_id($user_id) : false;
		$saved_card_id = $this->saved_cards->get_user_cards($user_id);
		$saved_card_id = array_keys($saved_card_id)[0];
		if ($profile_id) {
			$request = array(
				'merchid'   => $this->api_credentials['mid'],
				'amount'    => $amount * 100,
				'cvv2'      => wc_clean($_POST['card_connect-card-cvc']),
				'currency'  => "USD",
				'orderid'   => sprintf(__('%s - Order #%s', 'woocommerce'), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
				'name'      => trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
				'street'    => $order->billing_address_1,
				'city'      => $order->billing_city,
				'region'    => $order->billing_state,
				'country'   => $order->billing_country,
				'postal'    => $order->billing_postcode,
				'capture'   => $this->mode === 'capture' ? 'Y' : 'N',
				'profile'		=> "$profile_id/$saved_card_id",
			);

			if ( !is_null( $this->get_cc_client() ) {
				$response = $this->get_cc_client()->authorizeTransaction($request);
			} else {
				wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
				$order->add_order_note( 'CardConnect is not configured!' );
				return;
			}

			if('A' === $response['respstat']){

				$order_verification = $this->verify_customer_data($response);
				if(!$order_verification['is_valid']){

					$request = array(
						'merchid' => $this->api_credentials['mid'],
						'currency' => 'USD',
						'retref' => $response['retref'],
					);

					if ( !is_null( $this->get_cc_client() ) {
						$void_response = $this->get_cc_client()->voidTransaction($request);
					} else {
						wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
						$order->add_order_note( 'CardConnect is not configured!' );
						return;
					}

					if($void_response['authcode'] === 'REVERS'){
						$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
						foreach($order_verification['errors'] as $error){
							$order->add_order_note(sprintf(__( $error, 'woocommerce')));
							wc_add_notice(__('Payment error: ', 'woothemes') . $error, 'error');
						}
						return new WP_Error( 'error', 'failed transaction' );
					}
				}

				$order->payment_complete($response['retref']);
				update_post_meta($order_id, '_transaction_id', $response['retref']);

				// Reduce stock levels
				$order->reduce_order_stock();

				$order->add_order_note(sprintf(__( 'CardConnect payment approved (ID: %s, Authcode: %s)', 'woocommerce'), $response['retref'], $response['authcode']));

				return true;

			}else if('C' === $response['respstat']){
				$order->add_order_note(sprintf(__( 'CardConnect declined transaction. Response: %s', 'woocommerce'), $response['resptext']));
				wc_add_notice(__('Payment error: ', 'woothemes') . 'Order Declined : ' . $response['resptext'], 'error');
			}else{
				$order->add_order_note(sprintf(__( 'CardConnect failed transaction. Response: %s', 'woocommerce'), $response['resptext']));
				wc_add_notice(__('Payment error: ', 'woothemes') . 'An error prevented this transaction from completing. Please confirm your information and try again.', 'error');
			}
		}

		$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
		return new WP_Error( 'error', 'failed transaction' );
	}

	/**
	 * Don't transfer Card Connect customer/token meta when creating a parent renewal order.
	 *
	 * @access public
	 *
	 * @param array  $order_meta_query  MySQL query for pulling the metadata
	 * @param int    $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int    $renewal_order_id  Post ID of the order created for renewing the subscription
	 * @param string $new_order_role    The role the renewal order is taking, one of 'parent' or 'child'
	 *
	 * @return void
	 */
	public function remove_renewal_order_meta($order_meta_query, $original_order_id, $renewal_order_id, $new_order_role){
		if ('parent' == $new_order_role) {
        $order_meta_query .= " AND `meta_key` <> '_transaction_id' ";
    }
    return $order_meta_query;
	}

	/**
	 * Update the customer_id for a subscription after using Stripe to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Order $original_order   The original order in which the subscription was purchased.
	 * @param WC_Order $renewal_order    The order which recorded the successful payment (to make up for the failed
	 *                                   automatic payment).
	 * @param string   $subscription_key A subscription key of the form created by @see
	 *                                   WC_Subscriptions_Manager::get_subscription_key()
	 *
	 * @return void
	 */
	public function update_failing_payment_method($original_order, $renewal_order, $subscription_key){
		update_post_meta($old->id, '_transaction_id', get_post_meta($new->id, '_transaction_id', true));
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 *
	 * @param string   $payment_method_to_display the default payment method text to display
	 * @param array    $subscription_details      the subscription details
	 * @param WC_Order $order                     the order containing the subscription
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method($payment_method_to_display, $subscription_details, WC_Order $order){
		// bail for other payment methods
    if ( $this->id !== $order->recurring_payment_method || ! $order->customer_user )
        return $payment_method_to_display;
    return sprintf( __( 'Via %s', 'cardconnect-payment-gateway' ), $this->method_title );
	}
}
