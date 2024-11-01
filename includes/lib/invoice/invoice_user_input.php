<?php
require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/InvoiceCreator.php';
?>
<p class="form-row " id="billing_invoice_type_field" data-priority="200">
    <label for="billing_invoice_type" class=""><?php esc_html_e('發票開立', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
    <span class="woocommerce-input-wrapper">
        <select name="billing_invoice_type" id="billing_invoice_type" class="select " data-placeholder="">
            <option value="p"><?php esc_html_e('個人', 'zj-payment-packs'); ?></option>
            <option value="c"><?php esc_html_e('公司', 'zj-payment-packs'); ?></option>
            <option value="d"><?php esc_html_e('捐贈', 'zj-payment-packs'); ?></option>
        </select>
    </span>
</p>
<p class="form-row " id="billing_carruer_type_field" data-priority="210">
    <label for="billing_carruer_type" class=""><?php esc_html_e('載具類別', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
    <span class="woocommerce-input-wrapper">
        <select name="billing_carruer_type" id="billing_carruer_type" class="select " data-placeholder="">
            <?php if (!\ZJPP\InvoiceSystem::general_settings('invoice_disable_print')) { ?>
                <option value="0"><?php esc_html_e('索取紙本', 'zj-payment-packs'); ?></option>
            <?php } ?>
            <option value="1"><?php esc_html_e('雲端發票(中獎寄送紙本)', 'zj-payment-packs'); ?></option>
            <option value="2"><?php esc_html_e('自然人憑證', 'zj-payment-packs'); ?></option>
            <option value="3"><?php esc_html_e('手機條碼', 'zj-payment-packs'); ?></option>
        </select>
    </span>
</p>
<p class="form-row " id="billing_customer_identifier_field" data-priority="220" style="display: none;">
    <label for="billing_customer_identifier" class=""><?php esc_html_e('統一編號', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
    <span class="woocommerce-input-wrapper">
        <input type="text" class="input-text " name="billing_customer_identifier" id="billing_customer_identifier" placeholder="" value="">
    </span>
</p>
<p class="form-row " id="billing_love_code_field" data-priority="230" style="display: none;">
    <label for="billing_love_code" class=""><?php esc_html_e('捐贈單位', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
    <span class="woocommerce-input-wrapper">
        <select id="love_unit" name="love_unit">
            <option value="1995"><?php esc_html_e('生命線總會', 'zj-payment-packs'); ?></option>
            <option value="5757"><?php esc_html_e('婦幼協會', 'zj-payment-packs'); ?></option>
            <option value="other"><?php esc_html_e('自行輸入', 'zj-payment-packs'); ?></option>
        </select>
        <div id="billing_love_code_input" style="display: none;">
            <label><?php esc_html_e('捐贈代碼', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
            <input type="text" class="input-text " name="billing_love_code" id="billing_love_code" placeholder="" value="">
        </div>
    </span>
</p>
<p class="form-row " id="billing_carruer_num_field" data-priority="240" style="display: none;">
    <label for="billing_carruer_num" class=""><?php esc_html_e('載具編號', 'zj-payment-packs'); ?>&nbsp;<span class="optional"></span></label>
    <span class="woocommerce-input-wrapper">
        <input type="text" class="input-text" name="billing_carruer_num" id="billing_carruer_num" placeholder="" value="">
    </span>
</p>