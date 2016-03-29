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

		// handling for WooCommerce Subscriptions extension
		if(class_exists('WC_Subscriptions_Order')){

			// process subscription scheduled/renewal payments
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


		// handling for WooCommerce Pre-Orders extension
		if(class_exists('WC_Pre_Orders_Order')){

			// process pre-order payments upon "release"
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}

	}


	/**
	 * Process the payment
	 * If order contains subscriptions, use this class's process_subscription.
	 * If order contains pre-order, use this class's process_pre_order.
	 * Otherwise, use the parent class's process_payment function
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
		elseif ( WC_Pre_Orders_Order::order_contains_pre_order($order_id) ) {
			// Processing pre-order
			return $this->process_pre_order($order_id);
		}
		else {
			// Processing regular product
			return parent::process_payment($order_id);
		}
	}



	/***********************************************************************************************************
	 *
	 * CARDCONNECT API
	 *
	 ***********************************************************************************************************/



	/**
	 *
	 * !!! is the same for Subscriptions AND Pre-Orders
	 *
	 */
	public function generate_cardconnect_request($order, $amountToCharge=null, $checkoutFormData=null) {

		$user_id = $order->user_id;

		if ( is_null($amountToCharge) ) {
			$amountToCharge = $order->order_total * 100;
		}

		// these are the basics for a cardconnect API request
		$request = array(
			'merchid'   => $this->api_credentials['mid'],
			'amount'    => $amountToCharge,
			'currency'	=> $this->getCardConnectCurrencyCode($order->order_currency),
			'orderid'   => sprintf(__('%s - Order #%s', 'woocommerce'), esc_html(get_bloginfo('name', 'display')), $order->get_order_number()),
			'name'      => trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
			'address'   => $order->billing_address_1,
			'city'      => $order->billing_city,
			'region'    => $order->billing_state,
			'country'   => $order->billing_country,
			'postal'    => $order->billing_postcode,
			'capture'   => $this->mode === 'capture' ? 'Y' : 'N',
			'frontendid'=> $this->front_end_id,
		);


		if ( $checkoutFormData ) {
			$saved_card_id = $checkoutFormData['saved_card_id'];
			$profile_id = $checkoutFormData['profile_id'];
			$card_name = $checkoutFormData['card_name'];
			$store_new_card = $checkoutFormData['store_new_card'];
			$card_alias = $checkoutFormData['card_alias'];
			$cvv2 = $checkoutFormData['cvv2'];
			$expiry = $checkoutFormData['expiry'];
			$token = $checkoutFormData['token'];
		} else {
			return $request;
		}


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
				'cvv2'      => $cvv2,
				'expiry'	=> $expiry,
				'name'      => $card_name ? $card_name : trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
				'address'   => $order->billing_address_1,
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

			$request['profile'] = "$profile_id/$new_account_id";	// 20 digit profile_id/acctid to utilize a profile
			$request['expiry'] = $expiry;
			$request['cvv2'] =  $cvv2;

		}
		elseif ( !$profile_id && $store_new_card ) {
			// case 3:  user is paying with a new card and wants to "save this card"
			//			user is either NOT logged-in to WP or does NOT already have a profileid in USER meta


			// we'll create a new 'profile' with cardconnect
			$request['expiry'] = $expiry;
			$request['cvv2'] =  $cvv2;
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


			$request['expiry'] = $expiry;
			$request['cvv2'] =  $cvv2;
			$request['profile'] = 'Y';		// 'Y' will create a new account profile
			$request['account'] = $token;	// since we're using 'profile', 'account number' must be converted to a token
		}

		return $request;
	}


	/**
	 *
	 * used for BOTH Subscriptions AND Pre-Orders
	 *
	 */
	public function handle_cardconnect_response($payment_response, $order, $checkoutFormData) {

		$user_id = $order->user_id;

		$saved_card_id = $checkoutFormData['saved_card_id'];
		$profile_id = $checkoutFormData['profile_id'];
		$card_name = $checkoutFormData['card_name'];
		$store_new_card = $checkoutFormData['store_new_card'];
		$card_alias = $checkoutFormData['card_alias'];


		if ( (isset($payment_response['result'])) && ($payment_response['result'] != 'success') ) {
			// something went awry during the cardconnect API request

			wc_add_notice(__('Payment error: ', 'woothemes') . 'An error prevented this transaction from completing. Please confirm your information and try again.', 'error');
//			$order->add_order_note(sprintf(__( 'CardConnect failed transaction. Response: %s', 'woocommerce'), ''));

			$order->update_status('failed', __('Payment Failed', 'cardconnect-payment-gateway'));
			return;
		}
		else {
			// received a success response from the cardconnect API request

			// 4 cases to handle:
			if ( $saved_card_id ) {
				// case 1:  user paid by using a "saved card"

				// $payment_response['profileid'] is already saved in USER meta
				// $payment_response['acctid'] (aka 'saved card') will be saved in ORDER meta
			}
			elseif ( $profile_id ) {
				// case 2:  user is logged-in to WP and already has a profileid in USER meta
				//          !!! a *Subscription* will always require the card to be stored so that subsequent renewal orders
				//				can be processed.  We thus don't differentiate whether the user checked "save this card" or
				//				not when the purchase is for a *Subscription*. !!!
				//

				// $payment_response['acctid'] (aka 'saved card') will be saved in ORDER meta
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
				if (isset($this->saved_cards)) {
					// if the WP-admin cardconnect setting for 'Saved Cards - allow customers to save payment info' is
					// CHECKED, then we'll have a saved_cards object otherwise we will not.

					$this->saved_cards->set_user_profile_id($user_id, $payment_response['profileid']);
				}
			}
			return;

		}

	}



	/***********************************************************************************************************
	 *
	 * SUBSCRIPTIONS
	 *
	 ***********************************************************************************************************/



	/**
	 * called from action: woocommerce_scheduled_subscription_renewal_{$id}
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order            WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @return void
	 */
	public function scheduled_subscription_payment($amount_to_charge, $order){

		// Process the payment
		$result = $this->process_subscription_payment( $order, $amount_to_charge, null, false );

		// If the process results in error, then mark order as failed. If not, continue subscription
		if ( isset($result['result']) && ($result['result'] != 'success') ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}


	/**
	 * This is called when the subscription is INITIALLY purchased.
	 * It is also called when the Customer changes their Payment Method on a Subscription
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_subscription($order_id){

		// -----------------------------------------------------------------------
		// get details about the Order submitted at Checkout
		// -----------------------------------------------------------------------
		$order = new WC_Order($order_id);

		$user_id = $order->user_id;

		$checkoutFormData = parent::get_checkout_form_data($order, $user_id);

		if ( !$checkoutFormData['token'] && !$checkoutFormData['saved_card_id'] ) {
			parent::handleCheckoutFormDataError();
		}



		// -----------------------------------------------------------------------
		// use the data from the checkout form to generate a cardconnect API request array
		// -----------------------------------------------------------------------
		$request = $this->generate_cardconnect_request($order, null, $checkoutFormData);



		// -----------------------------------------------------------------------
		// perform the cardconnect API request to actually make the *SUBSCRIPTIONS* payment
		// -----------------------------------------------------------------------
		$payment_response = $this->process_subscription_payment($order, $order->get_total(), $request, true);


		// -----------------------------------------------------------------------
		// handle the response from the cardconnect API request
		// -----------------------------------------------------------------------
		$this->handle_cardconnect_response($payment_response, $order, $checkoutFormData);


		// should be either a "fail" or a "thankyou redirect" array
		return $payment_response;

	}




	/**
	 * This is called DIRECTLY for RECURRING subscription payments.
	 * This is also called BY process_subscription() for INITIAL subscription payments.
	 *
	 *
	 * @param mixed  $order
	 * @param int    $amount       (default: 0)
	 * @return
	 */
	public function process_subscription_payment($order = '', $amountToCharge = 0, $additionalRequest=null, $showNotices=false){
		global $woocommerce;


		// -----------------------------------------------------------------------
		// use the data from the checkout form to generate a cardconnect API request array
		// -----------------------------------------------------------------------
		$request = $this->generate_cardconnect_request($order, $amountToCharge, null);



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


		// dave
		// If the subscription has a 'free trial', the initial order will have an amount of 0.
		// In order for the cardconnect API to accept amount=0, we need to set capture=N.
		// This setup will then still allow for the credit card to be validated and stored with cardconnect
		//   (and in the META data) so that the ensuing renewal orders for the subscription can
		//   easily be processed once the free trial ends.
		// "Customer Change Payment Method" will also create an order with amount 0.

		if ( $amountToCharge == 0 ) {
			$request['capture'] = 'N';
			$request['amount'] = 0;
		}



		// -----------------------------------------------------------------------
		// check that cardconnect is configured in woocommerce and, if so, do the cardconnect API request !!
		// -----------------------------------------------------------------------

		if ( !is_null( $this->get_cc_client() ) ) {
			$response = $this->get_cc_client()->authorizeTransaction($request);
		} else {
			return parent::handleNoCardConnectConnection($order, $showNotices);
		}


		// -----------------------------------------------------------------------
		// handle the transaction response from the CardConnect API Rest Client
		// -----------------------------------------------------------------------

		if ( (!$response) || ('' === $response) ) {
			// got no response back from the CardConnect API endpoint request.
			// likely that the hosting server is unable to initiate/complete the CURL request to the API.

			return parent::handleAuthorizationResponse_NoResponse($order, $showNotices);
		}
		elseif( ($response['respstat']) && ('A' === $response['respstat']) ) {
			// 'A' response is for 'Approved'

			$order_verification = $this->verify_customer_data($response);
			if(!$order_verification['is_valid']){
				// failed either the AVS or CVV checks, therefore void this transaction.

				return parent::handleVerificationError($order, $order_verification, $response['retref'], $showNotices);

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

			// https://docs.woothemes.com/document/subscriptions/develop/payment-gateway-integration/upgrade-guide-for-subscriptions-v2-0/#section-8
			// payment_complete() will save _transaction_id to the ORDER META
			$order->payment_complete($response['retref']);

			// -----------------------------------------------------------------------


			$order->add_order_note(sprintf(__( 'CardConnect payment processed (ID: %s, Authcode: %s, Amount: %s)', 'woocommerce'), $response['retref'], $response['authcode'], get_woocommerce_currency_symbol() . ' ' . $response['amount']));

			// clear the shopping cart contents
			if ( $woocommerce->cart ) {
				$woocommerce->cart->empty_cart();
			}

			// Reduce stock levels
			// handled by payment_complete() call above
//			$order->reduce_order_stock();

			//--------------------------------
			// !!! Activate the SUBSCRIPTION
			//--------------------------------
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );


			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
				'profileid' => $response['profileid'],
				'acctid' => $response['acctid'],
			);

		}
		elseif( ($response['respstat']) && ('C' === $response['respstat']) ) {
			// 'C' response is for 'Declined'
			return parent::handleAuthorizationResponse_Declined($order, $response, $showNotices);
		}
		else{
			// 'B' response is for 'Retry'
			return parent::handleAuthorizationResponse_Retry($order, $response, $showNotices);
		}


		// catch-all error
		return parent::handleAuthorizationResponse_DefaultError($order, $showNotices);

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
	 * Update the meta data for a subscription after using CardConnect to complete a payment to make up for
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
				throw new Exception( 'New Payment Method\'s CardConnect ProfileID and Account ID are not valid!  Please try again' );
			}

		}
	}





	/***********************************************************************************************************
	 *
	 * PRE-ORDERS
	 *
	 ***********************************************************************************************************/




	/**
	 * determines whether we have an "upon release" or "up front" pre-order and chooses
	 * correct function to prepare/process the payment.
	 *
	 * "upon release" pre-orders will route to process_pre_order_payment() for payment upon "release"
	 * "up front" pre-orders route to parent::process_payment() to pay immediately.
	 *
	 */
	public function process_pre_order($order_id) {
		global $woocommerce;

		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			// charge/pay when this order is "released"


			// -----------------------------------------------------------------------
			// get details about the Order submitted at Checkout
			// -----------------------------------------------------------------------
			$order = new WC_Order($order_id);

			$user_id = $order->user_id;

			$checkoutFormData = parent::get_checkout_form_data($order, $user_id);


			if ( !$checkoutFormData['token'] && !$checkoutFormData['saved_card_id'] ) {
				return parent::handleCheckoutFormDataError();
			}



			// -----------------------------------------------------------------------
			// use the data from the checkout form to generate a cardconnect API request array
			// -----------------------------------------------------------------------
			$request = $this->generate_cardconnect_request($order, null, $checkoutFormData);


			// !!!! let's set some $request params specific to "upon release" PRE-ORDERS
			$request['capture'] = 'N';	// "prepare" instead of "process/charge" the order



			// -----------------------------------------------------------------------
			// perform the cardconnect API request to prepare for the eventual/future *PRE-ORDER* payment
			// -----------------------------------------------------------------------

			$prepare_payment_response = $this->process_pre_order_payment($order, $request, true);



			// -----------------------------------------------------------------------
			// handle the response from the cardconnect API request
			// -----------------------------------------------------------------------
			$this->handle_cardconnect_response($prepare_payment_response, $order, $checkoutFormData);


			if ( $prepare_payment_response && !isset($prepare_payment_response['result']) ) {

				// -----------------------------------------------------------------------
				// updating META data
				// -----------------------------------------------------------------------

				update_post_meta($order->id, '_wc_cardconnect_last4', substr(trim($prepare_payment_response['account']), -4));
				update_post_meta($order->id, '_wc_cardconnect_profileid', $prepare_payment_response['profileid']);
				update_post_meta($order->id, '_wc_cardconnect_acctid', $prepare_payment_response['acctid']);

				// -----------------------------------------------------------------------

				$order->add_order_note(sprintf(__( 'CardConnect pre-order payment pre-approved (ID: %s, Authcode: %s)', 'woocommerce'), $prepare_payment_response['retref'], $prepare_payment_response['authcode']));




				// clear the shopping cart
				if ( $woocommerce->cart ) {
					$woocommerce->cart->empty_cart();
				}

				//--------------------------------
				// !!! activate the PRE-ORDER
				//--------------------------------
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				// Reduce stock levels (this gets done by mark_order_as_pre_ordered() above
				//$order->reduce_order_stock();


				// Return thankyou redirect
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order)
				);
			}
			else {
				// should be a "fail" array
				return $prepare_payment_response;
			}


		} else {

			// charge/pay this order "up front" AKA immediately upon checkout
			return parent::process_payment($order_id);
		}

	}



	/**
	 * called for "upon release" pre-orders
	 *
	 * will be called by process_pre_order() with capture=N for "preparing/pre-authorizing" the pre-order
	 * will be called by process_pre_order_release_payment() with capture=Y for "charging" the pre-order
	 *
	 */
	public function process_pre_order_payment($order, $request, $showNotices=false) {

		// -----------------------------------------------------------------------
		// check that cardconnect is configured in woocommerce and, if so, do the cardconnect API request !!
		// -----------------------------------------------------------------------

		if ( !is_null( $this->get_cc_client() ) ) {
			$response = $this->get_cc_client()->authorizeTransaction($request);
		} else {
			return parent::handleNoCardConnectConnection($order, $showNotices);
		}


		// -----------------------------------------------------------------------
		// handle the transaction response from the CardConnect API Rest Client
		// -----------------------------------------------------------------------

		if ( (!$response) || ('' === $response) ) {
			// got no response back from the CardConnect API endpoint request.
			// likely that the hosting server is unable to initiate/complete the CURL request to the API.
			return parent::handleAuthorizationResponse_NoResponse($order, $showNotices);
		}
		elseif( ($response['respstat']) && ('A' === $response['respstat']) ) {
			// 'A' response is for 'Approved'

			$order_verification = $this->verify_customer_data($response);
			if(!$order_verification['is_valid']){
				// failed either the AVS or CVV checks, therefore void this transaction.

				return parent::handleVerificationError($order, $order_verification, $response['retref'], $showNotices);

			}
			else{
				return $response;
			}

		}
		elseif( ($response['respstat']) && ('C' === $response['respstat']) ) {
			// 'C' response is for 'Declined'
			return parent::handleAuthorizationResponse_Declined($order, $response, $showNotices);
		}
		else{
			// 'B' response is for 'Retry'
			return parent::handleAuthorizationResponse_Retry($order, $response, $showNotices);
		}


		// catch-all error
		return parent::handleAuthorizationResponse_DefaultError($order, $showNotices);

	}



	/**
	 *
	 * Called when the Pre-Order is "released"
	 * triggered by action: wc_pre_orders_process_pre_order_completion_payment_
	 *
	 * @param $order
	 */
	public function process_pre_order_release_payment( $order ) {

		$request = $this->generate_cardconnect_request($order, null, null);


		// get the payment token and add it to the $request
		$profileid = get_post_meta($order->id, '_wc_cardconnect_profileid', true);
		$acctid = get_post_meta($order->id, '_wc_cardconnect_acctid', true);
		$request['profile'] = "$profileid/$acctid";

		// attempt to charge the transaction
		$payment_response = $this->process_pre_order_payment($order, $request, false);


		if ( isset($payment_response['retref']) && isset($payment_response['authcode']) ) {
			// success!

			$order->add_order_note(sprintf(__( 'CardConnect pre-order payment processed (ID: %s, Authcode: %s, Amount: %s)', 'woocommerce'), $payment_response['retref'], $payment_response['authcode'], get_woocommerce_currency_symbol() . ' ' . $payment_response['amount']));

			// complete the order
			// payment_complete() will save _transaction_id to the ORDER META
			$order->payment_complete($payment_response['retref']);

		} else {

			// mark the order as failed
			return parent::handleAuthorizationResponse_Retry($order, $payment_response, false);
		}

	}

}
