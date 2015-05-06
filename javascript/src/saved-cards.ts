declare let jQuery : any;

export default class SavedCards {

	static init() : void {

		const SAVE_CARD_TOGGLE = '#card_connect-save-card';
		const CARD_NICKNAME = '#card_connect-new-card-alias';
		const WOOCOMMERCE_CREATE_ACCOUNT = '#createaccount';
		const CREATE_ACCOUNT_DISABLED_MESSAGE = 'You must check "Create an account" above in order to save your card.';
		const $ = jQuery;

		let $cardToggle = $(SAVE_CARD_TOGGLE);
		let $cardNickname = $(CARD_NICKNAME);
		let $createAccount = $(WOOCOMMERCE_CREATE_ACCOUNT);

		if($createAccount.length === 0) return;

		$cardToggle.on('change', controlNicknameField);
		$createAccount.on('change', controlSaveCardToggle);

		function controlNicknameField(){
			let isSet = $(this).is(':checked');
			$cardNickname.prop('disabled', !isSet);
			if(!isSet) $cardNickname.val('');
		}

		function controlSaveCardToggle(){
			let isSet = $(this).is(':checked');
			$cardToggle.prop('disabled', !isSet);
			if(!isSet) $cardToggle.prop('checked', false);
			setTooltips(isSet);
		}
		controlSaveCardToggle();

		function setTooltips(isEnabled){
			let titleText = isEnabled ? '' : CREATE_ACCOUNT_DISABLED_MESSAGE;
			$cardToggle.attr('title', titleText);
			$cardNickname.attr('title', titleText);
		}

	}

	static submitHandler() : void {

	}
}
