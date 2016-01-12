<p class="form-row form-row-first">
	<label for="card_connect-save-card">
		<input
			id="card_connect-save-card"
			class="input-checkbox"
			type="checkbox"
			name="card_connect-save-card"
			style="margin-right: 3px"
			/>
		<?php
			echo '<span id="card_connect-save-card-label-text">';
			echo __( 'Save this card', 'woocommerce' );
			echo '</span>';
		?>
	</label>
	<input
		id="card_connect-new-card-alias"
		class="input-text"
		type="text"
		name="card_connect-new-card-alias"
		placeholder="Card Nickname"
		disabled="true"
		/>
</p>

<?php if($saved_cards): ?>
	<p class="form-row form-row-last">
		<label for="card_connect-cards">
			<?php echo __( 'Use a saved card', 'woocommerce' ); ?>
		</label>
		<select
			id="card_connect-cards"
			class="input-select"
			name="card_connect-cards"
			>
			<option selected value="">My Saved Cards</option>
			<?php foreach($saved_cards as $id => $alias): ?>
				<option value="<?php echo $id; ?>"><?php echo $alias; ?></option>
			<?php endforeach; ?>
		</select>
	</p>
<?php endif; ?>