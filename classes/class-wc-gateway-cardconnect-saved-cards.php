<?php

if(!defined('ABSPATH')){
	exit; // Exit if accessed directly
}

class CardConnectSavedCards {

	const SAVED_CARDS_META_KEY = 'wc_cardconnect_saved_cards';
	const PROFILE_ID_META_KEY = 'wc_cardconnect_profile_id';

	private $client;
	private $mid;

	function __construct($client, $mid){
		$this->client = $client;
		$this->mid = $mid;
	}

	public function get_user_cards($user_id){
		$cards = get_user_meta($user_id, SAVED_CARDS_META_KEY, true);
		return !empty($cards) ? unserialize($cards) : false;
	}

	public function save_user_card($user_id, $card){
		$current_cards = $this->get_user_cards($user_id);
		$updated_cards = ($current_cards ? $current_cards : array()) + $card;
		return update_user_meta($user_id, SAVED_CARDS_META_KEY, serialize($updated_cards));
	}

	public function get_user_profile_id($user_id){
		$profile_id = get_user_meta($user_id, PROFILE_ID_META_KEY, true);
		return !empty($profile_id) ? $profile_id : false;
	}

	public function set_user_profile_id($user_id, $profile_id){
		return update_user_meta($user_id, PROFILE_ID_META_KEY, $profile_id);
	}

	public function add_account_to_profile($user_id, $card_alias, $request){
		$response = $this->client->profileCreate($request);
		$this->save_user_card($user_id, array( $response['acctid'] => $card_alias ));
		return $response['acctid'];
	}

}