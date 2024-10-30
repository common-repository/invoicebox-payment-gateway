<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-invoicebox-legal-form wc-payment-form" style="background:transparent;">
	<?php do_action( 'woocommerce_invoicebox_legal_form_start', $this->id );	?>
	<div class="form-row form-row-wide"><label><?php echo __('ИНН', "invoicebox"); ?> <span class="required">*</span></label>
		<input id="<?php echo esc_attr( $this->id ); ?>_inn" type="text" name="<?php echo esc_attr( $this->id ); ?>_inn" autocomplete="off">
	</div>
	<div class="form-row form-row-wide"><label><?php echo __('КПП', "invoicebox"); ?> <span class="required">*</span></label>
		<input id="<?php echo esc_attr( $this->id ); ?>_kpp" type="text" name="<?php echo esc_attr( $this->id ); ?>_kpp" autocomplete="off">
	</div>
    <div class="form-row form-row-wide"><label><?php echo __('Юридический адрес', "invoicebox"); ?> <span class="required">*</span></label>
		<input id="<?php echo esc_attr( $this->id ); ?>_address" type="text" name="<?php echo esc_attr( $this->id ); ?>_address" autocomplete="off">
	</div>
	<?php do_action( 'woocommerce_invoicebox_legal_form_end', $this->id ); ?>
	<div class="clear"></div>
</fieldset>

<?php
