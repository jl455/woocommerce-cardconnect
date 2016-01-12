<?php
/**
 * This class enables the gateway to integrate with the Subscriptions 2.x extension
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

			add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);


			// todo are these needed/used?
			// for orders that were previously called "parent" renewal orders
			add_filter('wcs_resubscribe_order_created', array($this, 'remove_resubscribe_order_meta'), 10);
			// for orders that were previously called "child" renewal orders
			add_filter('wcs_renewal_order_created', array($this, 'remove_renewal_order_meta'), 10);


			// Allow CUSTOMERS to manually change/set their payment method
			add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, array($this, 'update_failing_payment_method'), 10, 2);

			// display the current payment method used for a subscription in the "My Subscriptions" table
			add_filter('woocommerce_my_subscriptions_payment_method', array($this, 'maybe_render_subscription_payment_method'), 10, 2);

			// Allow STORE MANAGERS/ADMINS to manually change/set CardConnect as the payment method on a subscription (via wp-admin)
			add_filter('woocommerce_subscription_payment_meta', array($this, 'add_subscription_payment_meta'), 10, 2);
			add_filter('woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}
	}


	/**
	 * Process the payment
	 * If order contains subscriptions, use this class's process_subscription. If not, use the main class's process_payment function
	 *
	 * @param  int $order_id
	 * @return array
	 * @since 0.6.0
	 */
	public function process_payment($order_id){

		if (       ( function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order_id) )
				|| ( function_exists('wcs_is_subscription') && wcs_is_subscription($order_id) )
		   ) {

			// Processing subscription
			return $this->process_subscription($order_id);

		}
		else {
			// Processing regular product
			return parent::process_payment($order_id);
		}
	}


	/**
	 * called from action: woocommerce_scheduled_subscription_renewal_{$id}
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order            WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @return void
	 */
	public function scheduled_subscription_payment($amount_to_charge, $order){

		// Process the payment
		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		// If the process results in error, then mark order as failed. If not, continue subscription
		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}


	/**
	 * This is called when the subscription is INITIALLY purchased.
	 * It is also called when the Customer changes their Payment Method
	 *
	 * //Saves the card, if needed, and activates the subscription.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_subscription($order_id){
		global $woocommerce;


		// -----------------------------------------------------------------------
		// get details about the Order submitted at Checkout
		// -----------------------------------------------------------------------
		$order = new WC_Order($order_id);
//		$user_id = get_current_user_id();
		$user_id = $order->user_id;

		$profile_id = $this->profiles_enabled ? $this->saved_cards->get_user_profile_id($user_id) : false;

		// tokenized version of the user's credit card #
		$token = isset( $_POST['card_connect_token'] ) ? wc_clean( $_POST['card_connect_token'] ) : false;


		// correlates to the 'save this card' checkbox on the checkout form
		$store_new_card = isset($_POST['card_connect-save-card']) ? wc_clean($_POST['card_connect-save-card']) : false;

		// correlates to the 'card nickname' field on the checkout form
		$card_alias = isset($_POST['card_connect-new-card-alias']) ? wc_clean($_POST['card_connect-new-card-alias']) : '';
		if ( trim($card_alias) == '' ) {
//			$date = date("Y-m-d H:i:s");
			$date = date("Ymd-Hi");
			$card_alias = $order->billing_last_name . '_' . $date;
		} else {
			$card_alias = trim($card_alias);
		}

		// correlates to the 'cardholder name (if different)' field on the checkout form
		$card_name = isset( $_POST['card_connect-card-name'] ) ? wc_clean( $_POST['card_connect-card-name'] ) : false;

		// correlates to the 'use a saved card' field on the checkout form
		$saved_card_id = isset( $_POST['card_connect-cards'] ) ? wc_clean( $_POST['card_connect-cards'] ) : false;



		if(!$token && !$saved_card_id){
			wc_add_notice(__('Payment error: ', 'woothemes') . 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'error');
			return;
		}



		// -----------------------------------------------------------------------
		// create the cardconnect API request
		// -----------------------------------------------------------------------

		// this will hold all of the params sent in the cardconnect API request
		$request = array();

		// 4 cases to handle:
		if ( $saved_card_id ) {
			// case 1:  user is paying by using a "saved card"
			//			user must already be logged in to WP for this to be possible

			// use 'profile' param, no 'token' or 'account number' to pass
			$request['profile'] = "$profile_id/$saved_card_id";

		}
//		elseif ( $profile_id && $store_new_card ) {
		elseif ( $profile_id ) {
			// case 2:  user is logged-in to WP and already has a profileid in USER meta
			//          !!! a *Subscription* will always require the card to be stored (at cardconnect as an 'acctid', but NOT necessarily
			//				in USER meta unless $store_new_card=true) so that subsequent renewal orders
			//				can be processed.  We thus don't completely differentiate whether the user checked "save this card" or
			//				not when the purchase is for a *Subscription*. !!!
			//

			// we first need to ...
			// Get the new card's 'acctid'
			$profile_request = array(
				'merchid'   => $this->api_credentials['mid'],
				'profile'   => $profile_id,		// 20 digit profile_id to utilize a profile
				'account'  	=> $token,
				'cvv2'      => wc_clean($_POST['card_connect-card-cvc']),
				'expiry'	=> preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry'])),
				'name'      => $card_name ? $card_name : trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
				'street'    => $order->billing_address_1,
				'city'      => $order->billing_city,
				'region'    => $order->billing_state,
				'country'   => $order->billing_country,
				'postal'    => $order->billing_postcode,
				'frontendid'=> $this->front_end_id,
			);


			if ( $store_new_card ) {
				// we'll add this new 'saved card' to the USER meta
				$new_account_id = $this->saved_cards->add_account_to_profile($user_id, $card_alias, $profile_request);
			} else {
				// we'll just get the acctid but NOT save it in USER meta
				$new_account_id = $this->saved_cards->get_new_acctid($profile_request);
			}

//			$request['expiry'] = preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry']));
			$request['profile'] = "$profile_id/$new_account_id";	// 20 digit profile_id/acctid to utilize a profile

		}
		elseif ( !$profile_id && $store_new_card ) {
			// case 3:  user is paying with a new card and wants to "save this card"
			//			user is either NOT logged-in to WP or does NOT already have a profileid in USER meta


			// we'll create a new 'profile' with cardconnect
			$request['expiry'] = preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry']));
			$request['cvv2'] =  wc_clean($_POST['card_connect-card-cvc']);
			$request['profile'] = 'Y';		// 'Y' will create a new account profile
			$request['account'] = $token;	// since we're using 'profile', 'account number' must be converted to a token


			// In the $payment_response handling below, we'll need to associate this new card with
			// the profileid that gets returned in $payment_response.

		}
		else {
			// case 4:  user is simply paying with their card
			//			and
			//			user has NOT selected "save this card"
			//			and
			//			user had NOT selected "use a saved card"


			$request['expiry'] = preg_replace('/[^\d]/i','', wc_clean($_POST['card_connect-card-expiry']));
			$request['cvv2'] =  wc_clean($_POST['card_connect-card-cvc']);
			$request['profile'] = 'Y';		// 'Y' will create a new account profile
			$request['account'] = $token;	// since we're using 'profile', 'account number' must be converted to a token
		}


		// -----------------------------------------------------------------------
		// !!! perform the cardconnect API request to actually make the payment
		// -----------------------------------------------------------------------
		$payment_response = $this->process_subscription_payment($order, $order->get_total(), $request);


		// -----------------------------------------------------------------------
		// handle the response from the cardconnect API request
		// -----------------------------------------------------------------------
		if ( is_wp_error( $payment_response ) ) {
			// something went awry during the cardconnect API request

			wc_add_notice(__('Payment error: ', 'woothemes') . 'An error prevented this transaction from completing. Please confirm your information and try again.', 'error');
			$order->add_order_note(sprintf(__( 'CardConnect failed transaction. Response: %s', 'woocommerce'), $payment_response->get_error_message()));

			$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
			return;
		}
		else {
			// received a success response from the cardconnect API request

			// do the cart-related things that are not already done within process_subscription_payment();
			// Remove cart
			$woocommerce->cart->empty_cart();


			// 4 cases to handle:
			if ( $saved_card_id ) {
				// case 1:  user paid by using a "saved card"

				// $payment_response['profileid'] is already saved in USER meta
				// $payment_response['acctid'] (aka 'saved card') is already saved in USER meta
			}
			elseif ( $profile_id ) {
				// case 2:  user is logged-in to WP and already has a profileid in USER meta
				//          !!! a *Subscription* will always require the card to be stored so that subsequent renewal orders
				//				can be processed.  We thus don't differentiate whether the user checked "save this card" or
				//				not when the purchase is for a *Subscription*. !!!
				//

				// $payment_response['acctid'] (aka 'saved card') was already saved in USER meta in the above code
			}
			elseif ( !$profile_id && $store_new_card ) {
				// case 3:  user is paying with a new card and wants to "save this card"
				//			user is either NOT logged-in to WP or does NOT already have a profileid in USER meta

				// save the 'profile_id' to the USER meta
				$this->saved_cards->set_user_profile_id($user_id, $payment_response['profileid']);

				// save the saved card's 'acctid' to the USER meta
				$this->saved_cards->save_user_card($user_id, array($payment_response['acctid'] => $card_alias));
			}
			else {
				// case 4:  user is simply paying with their card
				//			and
				//			user had NOT selected "save this card"
				//			and
				//			user had NOT selected "use a saved card"

				// save the 'profile_id' to the USER meta
				$this->saved_cards->set_user_profile_id($user_id, $payment_response['profileid']);
			}


			// Activate the subscription
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			);

		}
	}




	/**
	 * This is called DIRECTLY for RECURRING subscription payments.
	 * This is also called BY process_subscription() for INITIAL subscription payments.
	 *
	 * Retrieve stored card and then process the transaction. If any errors, then return WP Error
	 *
	 *
	 * @param mixed  $order
	 * @param int    $amount       (default: 0)
	 * @return true | WP Error
	 */
	public function process_subscription_payment($order = '', $amount = 0, $additionalRequest=null){

		$request = array(
			'merchid'   => $this->api_credentials['mid'],
//			'cvv2'      => wc_clean($_POST['card_connect-card-cvc']),	// will be provided in $additionalRequest when needed
			'amount'    => $order->order_total * 100,
			'currency'  => "USD",
			'orderid'   => sprintf(__('%s - Order #%s', 'woocommerce'), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
			'name'      => trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
			'street'    => $order->billing_address_1,
			'city'      => $order->billing_city,
			'region'    => $order->billing_state,
			'country'   => $order->billing_country,
			'postal'    => $order->billing_postcode,
			'capture'   => $this->mode === 'capture' ? 'Y' : 'N',
			'frontendid'=> $this->front_end_id,
		);


		// dave
		// If the subscription has a 'free trial', the initial order will have an amount of 0.
		// In order for the cardconnect API to accept amount=0, we need to set capture=N.
		// This setup will then still allow for the credit card to be validated and stored with cardconnect
		//   (and in the META data) so that the ensuing renewal orders for the subscription can
		//   easily be processed once the free trial ends.

		if ( $request['amount'] == 0 ) {
			$request['capture'] = 'N';
		}


		// merge the $request params passed to this function with the local $request params
		if ( $additionalRequest ) {
			// will have $additionalRequest when handling an INITIAL order
			// will NOT have $additionalRequest when handling a RECURRING order
			$request = array_merge($request, $additionalRequest);
		}


		// 'profile' is provided as an $additionalRequest when this function is called from an INITIAL order.
		// 'profile' needs to be set when this function is called from a RECURRING order.
		if ( !$additionalRequest ) {

			// RECURRING orders should be billed to the same cardconnect "credit card" as used on the ORIGINAL order
			$profileid = get_post_meta($order->id, '_wc_cardconnect_profileid', true);
			$acctid = get_post_meta($order->id, '_wc_cardconnect_acctid', true);


			// special SOF hack to overcome the fact that our 1.x plugin did NOT store META data on the ORDER but
			// instead stored it on the USER.
			//
			// this will be applicable for Subscription Orders placed originally using 1.x but still active after an
			// upgrade to 2.x .  After the first renewal order for the particular subscription, the META data will be
			// correctly stored on the ORDERS/SUBSCRIPTIONS according to 2.0 design.

			if ( $profileid == '' ) {
				// read it from the USER meta
				// note: the meta field name is the old 'wc_cardconnect_profile_id' when on the USER meta
				$profileid = get_user_meta($order->user_id, 'wc_cardconnect_profile_id', true);


				// 1.x never stored 'acctid' so we'll have to defer to whatever cardconnect has as the default acctid.

				$request['profile'] = "$profileid";
			} else {
				// 2.0 goodness

				$request['profile'] = "$profileid/$acctid";
			}

		}


		// -----------------------------------------------------------------------
		// !! check that cardconnect is configured in woocommerce and, if so, do the cardconnect API request !!
		// -----------------------------------------------------------------------

		if ( !is_null( $this->get_cc_client() ) ) {
			$response = $this->get_cc_client()->authorizeTransaction($request);
		} else {
//			wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
			$order->add_order_note( 'CardConnect is not configured!' );
			return new WP_Error( 'error', 'CardConnect is not configured!' );
		}


		// handle the transaction response from the CardConnect API Rest Client

		if ( (!$response) || ('' === $response) ) {
			// got no response back from the CardConnect API endpoint request.
			// likely that the hosting server is unable to initiate/complete the CURL request to the API.
			wc_add_notice(__('Payment error: ', 'woothemes') . 'A critical server error prevented this transaction from completing. Please confirm your information and try again.', 'error');
			$order->add_order_note(sprintf(__('CardConnect failed transaction. Response: %s', 'woocommerce'), 'CURL error?'));
		}
		elseif( ($response['respstat']) && ('A' === $response['respstat']) ) {
			// 'A' response is for 'Approved'

			$order_verification = $this->verify_customer_data($response);
			if(!$order_verification['is_valid']){
				// failed either the AVS or CVV checks, therefore void this transaction.

				$request = array(
					'merchid' 	=> $this->api_credentials['mid'],
					'currency' 	=> 'USD',
					'retref' 	=> $response['retref'],
					'frontendid'=> $this->front_end_id,
				);

				if ( !is_null( $this->get_cc_client() ) ) {
					$void_response = $this->get_cc_client()->voidTransaction($request);
				} else {
//					wc_add_notice(__('Payment error: ', 'woothemes') . 'CardConnect is not configured! ', 'error');
					$order->add_order_note( 'CardConnect is not configured!' );
					return new WP_Error( 'error', 'CardConnect is not configured!' );
				}

				if($void_response['authcode'] === 'REVERS'){
					$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
					foreach($order_verification['errors'] as $error){
						$order->add_order_note(sprintf(__( $error, 'woocommerce')));
//						wc_add_notice(__('Payment error: ', 'woothemes') . $error, 'error');
					}
					return new WP_Error( 'error', 'failed transaction ' );
				}
			}



			// -----------------------------------------------------------------------
			// updating META data
			// -----------------------------------------------------------------------

			update_post_meta($order->id, '_wc_cardconnect_last4', substr(trim($response['account']), -4));
			update_post_meta($order->id, '_wc_cardconnect_profileid', $response['profileid']);
			update_post_meta($order->id, '_wc_cardconnect_acctid', $response['acctid']);

			// Also save meta info on the Subscriptions being purchased in the order
			foreach( wcs_get_subscriptions_for_order( $order->id ) as $subscription ) {
				update_post_meta($subscription->id, '_wc_cardconnect_last4', substr(trim($response['account']), -4));
				update_post_meta($subscription->id, '_wc_cardconnect_profileid', $response['profileid']);
				update_post_meta($subscription->id, '_wc_cardconnect_acctid', $response['acctid']);
			}

			// save the cardconnect 'Retrieval Reference Number' as _transaction_id on the Order's post_meta
			// update_post_meta($order->id, '_transaction_id', $response['retref']);	// dupe of '_transaction_id'
			// https://docs.woothemes.com/document/subscriptions/develop/payment-gateway-integration/upgrade-guide-for-subscriptions-v2-0/#section-8
			// payment_complete() will save _transaction_id
			$order->payment_complete($response['retref']);

			// -----------------------------------------------------------------------



			// Reduce stock levels
			$order->reduce_order_stock();

			$order->add_order_note(sprintf(__( 'CardConnect payment approved (ID: %s, Authcode: %s)', 'woocommerce'), $response['retref'], $response['authcode']));

			return $response;

		}
		elseif( ($response['respstat']) && ('C' === $response['respstat']) ) {
			// 'C' response if for 'Declined'

			$order->add_order_note(sprintf(__( 'CardConnect declined transaction. Response: %s', 'woocommerce'), $response['resptext']));
			$order->update_status('failed', __('Payment Declined', 'cardconnect-payment-gateway'));

//			wc_add_notice(__('Payment error: ', 'woothemes') . 'Order Declined : ' . $response['resptext'], 'error');
			return new WP_Error( 'error', 'Order Declined : ' . $response['resptext'] );
		}
		else{
			// B - Retry

			$order->add_order_note(sprintf(__( 'CardConnect failed transaction. Response: %s', 'woocommerce'), $response['resptext']));
			$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));

//			wc_add_notice(__('Payment error: ', 'woothemes') . 'An error prevented this transaction from completing. Please confirm your information and try again.', 'error');
			return new WP_Error( 'error', 'An error prevented this transaction from completing. Please confirm your information and try again.' );
		}


		// catch-all error
		$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
		return new WP_Error( 'error', 'failed transaction' );

	}




	/**
	 * Don't transfer Card Connect customer/token meta when creating a parent renewal order.
	 *
	 * @access public
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */

	// old - from Subscriptions 1.x
//	public function remove_resubscribe_order_meta($order_meta_query, $original_order_id, $renewal_order_id, $new_order_role){
//		if ('parent' == $new_order_role) {
//        	$order_meta_query .= " AND `meta_key` <> '_transaction_id' ";
//    	}
//    	return $order_meta_query;
//
//		delete_post_meta( $resubscribe_order->id, '_simplify_customer_id' );
//	}


	// dave - i don't think these are needed in 2.0
	// havent seen meta _transaction_id incorrectly assigned to an order yet...
	public function remove_resubscribe_order_meta( $resubscribe_order ) {
//		delete_post_meta( $resubscribe_order->id, '_transaction_id' );
		return $resubscribe_order;
	}


	public function remove_renewal_order_meta( $renewal_order ) {
//		delete_post_meta( $renewal_order->id, '_transaction_id' );
		return $renewal_order;
	}


	/**
	 * Update the customer_id for a subscription after using CardConnect to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {

		$last4 = get_post_meta($renewal_order->id, '_wc_cardconnect_last4', true);
		$profileid = get_post_meta($renewal_order->id, '_wc_cardconnect_profileid', true);
		$acctid = get_post_meta($renewal_order->id, '_wc_cardconnect_acctid', true);


		// Also save meta info on the Subscription that this renewal order is tied to
		$subscription_id = get_post_meta($renewal_order->id, '_subscription_renewal', true);
		update_post_meta($subscription_id, '_wc_cardconnect_last4', $last4);
		update_post_meta($subscription_id, '_wc_cardconnect_profileid', $profileid);
		update_post_meta($subscription_id, '_wc_cardconnect_acctid', $acctid);
	}


	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string   $payment_method_to_display the default payment method text to display
	 * @param array    $subscription_details      the subscription details
	 * @param WC_Order $order                     the order containing the subscription
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method($payment_method_to_display, $subscription){
		// bail for other payment methods
		if ( $this->id !== $subscription->payment_gateway->id ) {
			return $payment_method_to_display;
		} else {
			// might be nice if this displayed the 'saved card name' or the last 4 digits of the credit card used
			// it currently returns and thus displays "Via CardConnect"
//			return sprintf( __( 'Via %s', 'cardconnect-payment-gateway' ), $this->method_title );

			$last4 = get_post_meta($subscription->id, '_wc_cardconnect_last4', true);
			if ( trim($last4) != '' ) {
				$last4 = 'via credit card ending in x' . $last4;
			} else {
				$last4 = 'via credit card';
			}
			return sprintf( __( '%s', 'cardconnect-payment-gateway' ), $last4 );
		}
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {

		$profileid = get_post_meta($subscription->id, '_wc_cardconnect_profileid', true);
		$acctid = get_post_meta($subscription->id, '_wc_cardconnect_acctid', true);

		// note: We are purposely NOT showing the last4 since this (stale) value will overwrite the correct
		//       last4 that is being set in the Subscription's META data by the validation_ function below.

//		$last4 = get_post_meta($subscription->id, '_wc_cardconnect_last4', true);

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_wc_cardconnect_profileid' => array(
					'value' => $profileid,
					'label' => 'CardConnect Profile ID',
				),
				'_wc_cardconnect_acctid' => array(
					'value' => $acctid,
					'label' => 'CardConnect Account ID',
				),
//				'_wc_cardconnect_last4' => array(
//					'value' => $last4,
//					'label' => 'credit card last 4 digits',
//				),
			),
		);

		return $payment_meta;
	}



	/**
	 *
	 * https://docs.woothemes.com/document/subscriptions/develop/payment-gateway-integration/change-payment-method-admin/#section-4
	 *
	 */
	function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {

		if ( $this->id === $payment_method_id ) {

			// check that 'CardConnect Profile ID' is not empty
			if ( ! isset( $payment_meta['post_meta']['_wc_cardconnect_profileid']['value'] ) || empty( $payment_meta['post_meta']['_wc_cardconnect_profileid']['value'] ) ) {
				throw new Exception( 'New payment method CardConnect is missing a Profile ID' );
			}

			// check that 'CardConnect Acct ID' is not empty
			if ( ! isset( $payment_meta['post_meta']['_wc_cardconnect_acctid']['value'] ) || empty( $payment_meta['post_meta']['_wc_cardconnect_acctid']['value'] ) ) {
				throw new Exception( 'New payment method CardConnect is missing an Account ID' );
			}


			// check that this 'CardConnect ProfileId/Acctid' combo is valid.

			$profileid = trim($payment_meta['post_meta']['_wc_cardconnect_profileid']['value']);
			$acctid = trim($payment_meta['post_meta']['_wc_cardconnect_acctid']['value']);

			$merchantid = $this->api_credentials['mid'];

			$response = $this->get_cc_client()->profileGet($profileid, $acctid, $merchantid);

			if ( isset($response[0]) && isset($response[0]['token']) ) {

				// update the 'last4' on the Subscription's META
				$last4 = substr(trim($response[0]['token']), -4);
				$subscription_id = $_POST['post_ID'];
				update_post_meta($subscription_id, '_wc_cardconnect_last4', $last4);

				return true;
			} else {
				throw new Exception( 'New Payment Method\'s CardConnect ProfileID and Account ID are not valid!\nPlease try again' );
			}

		}
	}


}
