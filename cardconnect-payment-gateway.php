<?php
/**
 * Plugin Name: CardConnect Payment Gateway
 * Plugin URI: http://sofcorp.com/
 * Description: Accept credit card payments in your WooCommerce store!
 * Version: 1.0.2
 * Author: SOF Inc <gregp@sofcorp.com>
 * Author URI: http://sofcorp.com
 * License: GNU General Public License v2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @version 1.0.2
 * @author Sof, Inc
 */

/*
	Copyright: © 2015 SOF Inc <gregp@sofcorp.com>.
*/

if(!defined('ABSPATH')){
	exit; // Exit if accessed directly
}

define('WC_CARDCONNECT_VER', '0.5.0');
define('WC_CARDCONNECT_PLUGIN_PATH', untrailingslashit(plugin_basename(__DIR__)));
define('WC_CARDCONNECT_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');
define('WC_CARDCONNECT_PLUGIN_URL', untrailingslashit(plugins_url('', __FILE__)));

add_action('plugins_loaded', 'CardConnectPaymentGateway_init', 0);

/**
 * Initializes Card Connect Gateway
 *
 * @return void
 * @since 0.5.0
 */
function CardConnectPaymentGateway_init(){

	// Append local includes dir to include path
	set_include_path(get_include_path() . PATH_SEPARATOR . plugin_dir_path(__FILE__) . 'includes');

	if(class_exists('CardConnectPaymentGateway') || !class_exists('WC_Payment_Gateway')){
		return;
	}

	// Include Classes
	include_once 'classes/class-wc-gateway-cardconnect.php';
	include_once 'classes/class-wc-gateway-cardconnect-saved-cards.php';
	if(class_exists('WC_Subscriptions_Order')){
		include_once 'classes/class-wc-gateway-cardconnect-addons.php';
	}


	/**
	 * Add the Gateway to WooCommerce
	 **/
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_CardConnectPaymentGateway');
	function woocommerce_add_gateway_CardConnectPaymentGateway($methods){
		if(class_exists('WC_Subscriptions_Order')){
			$methods[] = 'CardConnectPaymentGatewayAddons';
		}else{
			$methods[] = 'CardConnectPaymentGateway';
		}
		return $methods;
	}

}
