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


	/*
	 * Returns an ?array? of the saved cards in USER meta
	 */
	public function get_user_cards($user_id){
		$cards = get_user_meta($user_id, self::SAVED_CARDS_META_KEY, true);
		return !empty($cards) ? unserialize($cards) : false;
	}


	/*
	 * Updates USER meta for key 'wc_cardconnect_saved_cards'
	 */
	public function save_user_card($user_id, $card){
		$current_cards = $this->get_user_cards($user_id);
		$updated_cards = ($current_cards ? $current_cards : array()) + $card;
		return update_user_meta($user_id, self::SAVED_CARDS_META_KEY, serialize($updated_cards));
	}


	/*
	 * Returns USER meta for key 'wc_cardconnect_profile_id'
	 */
	public function get_user_profile_id($user_id){
		$profile_id = get_user_meta($user_id, self::PROFILE_ID_META_KEY, true);
		return !empty($profile_id) ? $profile_id : false;
	}


	/*
	 * Updates USER meta for key 'wc_cardconnect_profile_id'
	 */
	public function set_user_profile_id($user_id, $profile_id){
		return update_user_meta($user_id, self::PROFILE_ID_META_KEY, $profile_id);
	}


	/**
	 * Gets a new acctid and then adds this 'saved card' (aka 'acctid') to the USER meta
	 * Returns the 'acctid'
	 */
	public function add_account_to_profile($user_id, $card_alias, $request){

		$acctid = $this->get_new_acctid($request);

		// 'acctid' is the "account identifier within a profile"
		// in other words, it corresponds to the 'saved card' within that WP user's card connect Profile

		if ( !is_null($acctid) ) {
			$this->save_user_card($user_id, array( $acctid => $card_alias ));
		}

		return $acctid;
	}


	/**
	 * perform the cardconnect API request to get a new acctid
	 *
	 * Returns the 'acctid';
	 *
	 */
	public function get_new_acctid($request) {
		$response = $this->client->profileCreate($request);
		if ( isset($response['acctid']) ) {
			return $response['acctid'];
		} else {
			return null;
		}
	}

}
