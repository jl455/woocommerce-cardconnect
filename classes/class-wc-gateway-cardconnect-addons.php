<?php

class CardConnectPaymentGatewayAddons extends CardConnectPaymentGateway{

	public function __construct(){
		parent::__construct();
		if(class_exists('WC_Subscriptions_Order')){
			add_action('scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 3);
			add_filter('woocommerce_subscriptions_renewal_order_meta_query', array($this, 'remove_renewal_order_meta'), 10, 4);
			add_action('woocommerce_subscriptions_changed_failing_payment_method_stripe', array($this, 'update_failing_payment_method'), 10, 3);
			// display the current payment method used for a subscription in the "My Subscriptions" table
			add_filter('woocommerce_my_subscriptions_recurring_payment_method', array($this, 'maybe_render_subscription_payment_method'), 10, 3);
		}
	}

	/**
	 * Process the subscription
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_subscription($order_id, $retry = true){
		// @TODO: Implement
	}


	/**
	 * Process the payment
	 *
	 * @param  int $order_id
	 *
	 * @return array
	 */
	public function process_payment($order_id, $retry = true){
		// Processing subscription
		if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($order_id)){
			return $this->process_subscription($order_id, $retry);
		// Processing regular product
		}else{
			return parent::process_payment($order_id, $retry);
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
	 */
	public function scheduled_subscription_payment($amount_to_charge, $order, $product_id){
		// @TODO: Implement
	}

	/**
	 * process_subscription_payment function.
	 *
	 * @access public
	 *
	 * @param mixed  $order
	 * @param int    $amount       (default: 0)
	 *
	 * @return void
	 */
	public function process_subscription_payment($order = '', $amount = 0){
		// @TODO: Implement
	}

	/**
	 * Don't transfer Stripe customer/token meta when creating a parent renewal order.
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
		// @TODO: Implement
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
		// @TODO: Implement
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
		// @TODO: Implement
	}

	/**
	 * Process a pre-order payment when the pre-order is released
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function process_pre_order_release_payment($order){
		// @TODO: Implement
	}

}