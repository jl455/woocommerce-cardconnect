<fieldset id="card_connect-cc-form">
	<p class="form-row form-row-wide">
		<p style="margin: 0 0 5px;">Accepting:</p>
		<ul class="card-connect-allowed-cards"><?php echo $card_icons; ?></ul>
	</p>
	<?php if($profiles_enabled){
		wc_get_template(
			'saved-cards.php',
			array(

			),
			WC_CARDCONNECT_PLUGIN_PATH,
			WC_CARDCONNECT_TEMPLATE_PATH
		);
	} ?>
	<p class="form-row form-row-wide">
		<label for="card_connect-card-name">
			<?php echo __( 'Cardholder Name (If Different)', 'woocommerce' ); ?>
		</label>
		<input
			id="card_connect-card-name"
			class="input-text"
			type="text"
			maxlength="25"
			name="card_connect-card-name"
			/>
	</p>
	<p class="form-row form-row-wide">
		<label for="card_connect-card-number">
			<?php echo __( 'Card Number', 'woocommerce' ); ?>
			<span class="required">*</span>
		</label>
		<input
			id="card_connect-card-number"
			class="input-text wc-credit-card-form-card-number"
			type="text"
			maxlength="20"
			autocomplete="off"
			placeholder="•••• •••• •••• ••••"
			<?php echo is_sandbox ? 'value="4242 4242 4242 4242"' : '';?>
			/>
	<div class="js-card-connect-errors"></div>
	</p>
	<p class="form-row form-row-first">
		<label for="card_connect-card-expiry">
			<?php echo __( 'Expiry (MM/YY)', 'woocommerce' ); ?>
			<span class="required">*</span>
		</label>
		<input
			id="card_connect-card-expiry"
			class="input-text wc-credit-card-form-card-expiry"
		  type="text"
		  autocomplete="off"
		  placeholder="<?php echo __( 'MM / YY', 'woocommerce' ); ?>"
			name="card_connect-card-expiry"
			<?php echo is_sandbox ? 'value="12 / 25"' : ''; ?>
			/>
	</p>
	<p class="form-row form-row-last">
		<label for="card_connect-card-cvc">
			<?php echo __( 'Card Code', 'woocommerce' ); ?>
			<span class="required">*</span>
		</label>
		<input
			id="card_connect-card-cvc"
			class="input-text wc-credit-card-form-card-cvc"
			type="text"
			autocomplete="off"
			placeholder="<?php echo __( 'CVC', 'woocommerce' ); ?>"
			name="card_connect-card-cvc"
			<?php echo is_sandbox ? 'value="123"' : ''; ?>
			/>
		<em><?php echo __( 'Your CVV number will not be stored on sever.', 'woocommerce' ); ?></em>
	</p>
</fieldset>