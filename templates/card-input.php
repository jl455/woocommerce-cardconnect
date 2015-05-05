<p class="form-row form-row-wide">
	<p style="margin: 0 0 5px;">Accepting:</p>
	<ul class="card-connect-allowed-cards">'<?php echo $card_icons; ?>'</ul>
</p>

<p class="form-row form-row-wide">
	<label for="<?php echo esc_attr( $this->id ); ?>-card-name"><?php echo __( 'Cardholder Name (If Different)', 'woocommerce' ); ?></label>
	<input id="<?php echo esc_attr( $this->id ); ?>-card-name" class="input-text " type="text" maxlength="25" name="<?php echo $this->id; ?>-card-name"/>
</p>
<p class="form-row form-row-wide">
	<label for="<?php echo esc_attr( $this->id ); ?>-card-number"><?php echo __( 'Card Number', 'woocommerce' ); ?><span class="required">*</span></label>
	<input id="<?php echo esc_attr( $this->id ); ?>-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" <?php echo $isSandbox ? 'value="4242 4242 4242 4242"' : '';?>/>
<div class="js-card-connect-errors"></div>
</p>
<p class="form-row form-row-first">
	<label for="<?php echo esc_attr( $this->id ); ?>-card-expiry"><?php echo __( 'Expiry (MM/YY)', 'woocommerce' ); ?><span class="required">*</span></label>
	<input id="<?php echo esc_attr( $this->id ); ?>-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="<?php echo __( 'MM / YY', 'woocommerce' ); ?>" name="<?php echo $this->id; ?>-card-expiry" <?php echo $isSandbox ? 'value="12 / 25"' : ''; ?>/>
</p>
<p class="form-row form-row-last">
	<label for="<?php echo esc_attr( $this->id ); ?>-card-cvc"><?php echo __( 'Card Code', 'woocommerce' ); ?><span class="required">*</span></label>
	<input id="<?php echo esc_attr( $this->id ); ?>-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="<?php echo __( 'CVC', 'woocommerce' ); ?>" name="<?php echo $this->id; ?>-card-cvc" <?php echo $isSandbox ? 'value="123"' : ''; ?>/>
	<em><?php echo __( 'Your CVV number will not be stored on sever.', 'woocommerce' ); ?></em>
</p>