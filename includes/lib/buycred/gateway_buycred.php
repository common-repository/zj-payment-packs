<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

return new class(array()) extends \myCRED_Payment_Gateway
{
    use embedded_payment_system;
    /**
     * Construct
     */
    public function __construct($gateway_prefs)
    {
        $types            = mycred_get_types();
        $default_exchange = array();
        foreach ($types as $type => $label)
            $default_exchange[$type] = 1;
        if ($this->system()) {
            $this->system()->order_system()->set_data([
                'gateway_obj' => $this,
            ]);
            $data = $this->system()->gateway_data();
            parent::__construct(array(
                'id'               => $this->system()->gateway_name(),
                'label'            => $data['method_label'],
                'gateway_logo_url' => trailingslashit(ZJPP_PLUGIN_URL) . 'assets/images/ecpay.png',
                'defaults'         => array(
                    'sandbox'          => 0,
                    'currency'         => '',
                    'merchant_id'      => '',
                    'hash_key'         => '',
                    'hash_IV'          => '',
                    'item_name'        => 'Purchase of myCRED %plural%',
                    'item_desc'        => '',
                    'logo_url'         => '',
                    'exchange'         => $default_exchange
                )
            ), $gateway_prefs);
            add_filter('mycred_buycred_populate_transaction', array($this, 'order_populate_transaction'), 10, 2);
        }
    }
    /**
     * populate_transaction
     *
     * @param  mixed $res
     * @param  mixed $id
     * @return void
     */
    public function order_populate_transaction($res, $id)
    {
        $this->transcation_id = $this->post_id = false;
        return false;
    }
    /**
     * Process Handler
     * @since 0.1
     * @version 1.3
     */
    public function process()
    {
        do_action('zjpp_postback_core', $this->system());
    }

    /**
     * Results Handler
     * @since 0.1
     * @version 1.0.1
     */
    public function returning()
    {
    }

    /**
     * Prep Sale
     * @since 1.8
     * @version 1.0
     */
    public function prep_sale($new_transaction = false)
    {
        $this->system()->start_payment_process();
    }

    public function checkout_header()
    {
        if ($this->sandbox_mode) {
?>
            <div class="checkout-header">
                <div class="warning"><?php echo esc_js(esc_attr(__('Test Mode', 'mycred'))); ?></div>
            </div>;
        <?php
        }
        $sandbox = (!$this->sandbox_mode) ? ' no-header' : '';
        ?>
        <div class="checkout-body padded'<?php echo esc_attr($sandbox); ?>">
            <?php
        }
        /**
         * Checkout Logo
         * @since 1.8
         * @version 1.0
         */
        public function checkout_logo($title = '')
        {
            if ($title === '') {
                if (isset($this->prefs['title'])) $title = $this->prefs['title'];
                elseif (isset($this->prefs['label'])) $title = $this->prefs['label'];
            }

            if (isset($this->prefs['logo']) && !empty($this->prefs['logo'])) { ?>
                <img src="<?php echo esc_attr($this->prefs['logo']); ?>" alt="" />
            <?php
            } elseif (isset($this->prefs['logo_url']) && !empty($this->prefs['logo_url'])) { ?>
                <img src="<?php echo esc_attr($this->prefs['logo_url']); ?>" alt="" />
            <?php
            } elseif (isset($this->gateway_logo_url) && !empty($this->gateway_logo_url)) { ?>
                <img src="<?php echo esc_attr($this->gateway_logo_url); ?>" alt="" />'
            <?php
            } elseif ($title !== false) { ?>
                <h2 class="gateway-title"><?php echo esc_html($title); ?></h2>'
            <?php
            }
        }

        /**
         * Checkout: Order
         * @since 1.8
         * @version 1.0
         */
        public function checkout_order()
        {
            $point_type_name = apply_filters('mycred_buycred_checkout_order', $this->core->plural(), $this);
            $item_label = apply_filters('mycred_buycred_checkout_order', __('Item', 'mycred'), $this);
            $amount_label = apply_filters('mycred_buycred_checkout_order', __('Amount', 'mycred'), $this);
            $cost_label = apply_filters('mycred_buycred_checkout_order', __('Cost', 'mycred'), $this);
            ?>
            <table class="table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th class="item"><?php echo esc_js(esc_attr($item_label)); ?></td>
                        <th class="cost right"><?php esc_js(esc_attr($amount_label)); ?></td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="item"><?php echo esc_html($point_type_name); ?></td>
                        <td class="cost right"><?php esc_html($this->amount); ?></td>
                    </tr>
                    <?php
                    if ($this->gifting) { ?>
                        <tr>
                            <td colspan="2"><strong><?php echo esc_js(esc_attr($cost_label)); ?>:</strong><?php echo esc_html(get_userdata($this->recipient_id)->display_name); ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="total">
                        <td class="item right"><?php echo esc_js(esc_attr(__('Cost', 'mycred'))); ?></td>
                        <td class="cost right"><?php echo esc_html(sprintf('%s %s', apply_filters('mycred_buycred_display_user_amount', $this->cost), $this->prefs['currency'])); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php
        }
        /**
         * Checkout: Cancel
         * @since 1.8
         * @version 1.0
         */
        public function checkout_cancel()
        { ?>
            <hr />
            <div class="cancel">
                <a href="<?php echo esc_url($this->get_cancelled($this->transaction_id)); ?>">
                    <?php echo esc_js(esc_attr(__('cancel purchase', 'mycred'))); ?>
                </a>
            </div>
            <?php
        }
        /**
         * Checkout Footer
         * @since 1.8
         * @version 1.0
         */
        public function checkout_footer($button_label = '')
        {
            if ($button_label == '')
                $button_label = __('Continue', 'mycred');

            if (!empty($this->redirect_fields)) {

                $fields = apply_filters('mycred_buycred_redirect_fields', $this->redirect_fields, $this);

                if (!empty($fields)) {
                    foreach ($fields as $name => $value) { ?>
                        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_html($value); ?>" />
            <?php
                    }
                }
            }
            ?>
            <div class="checkout-footer"><?php if (!empty($this->toggle_id)) { ?>
                    <button type="button" id="checkout-action-button" data-act="toggle" data-value="<?php echo esc_attr($this->toggle_id); ?>" class="btn btn-default"><?php echo esc_js($button_label); ?></button>
                <?php } else if (!empty($this->redirect_to)) { ?>
                    <button type="button" id="checkout-action-button" data-act="redirect" data-value="<?php esc_attr($this->redirect_to); ?>" class="btn btn-default <?php echo esc_attr($this->id); ?>"><?php echo esc_js($button_label); ?></button>
                <?php } else { ?>
                    <button type="button" id="checkout-action-button" data-act="submit" data-value="" class="btn btn-default"><?php echo esc_js($button_label); ?></button>
                <?php } ?>
            </div>
        <?php
        }
        /**
         * AJAX Buy Handler
         * @since 1.8
         * @version 1.0
         */
        public function ajax_buy()
        {
            ob_start();
            // Construct the checkout box content
            $this->checkout_header();
            $this->checkout_logo();
            $this->checkout_order();
            $this->checkout_cancel();
            $this->checkout_footer();

            // Return a JSON response
            $this->send_json(ob_get_clean());
        }

        /**
         * Checkout Page Body
         * This gateway only uses the checkout body.
         * @since 1.8
         * @version 1.0
         */
        public function checkout_page_body()
        {
            $this->checkout_header();
            $this->checkout_logo(false);
            $this->checkout_order();
            $this->checkout_cancel();
            $this->checkout_footer();
        }

        /**
         * Preferences
         * @since 0.1
         * @version 1.0
         */
        public function preferences()
        {
            $prefs = $this->prefs;
        ?>
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                    <h3><?php _e('Details', 'mycred'); ?> of Gateway ID: <?php echo esc_attr($this->system()->gateway_name()); ?></h3>
                    <div class="form-group">
                        <label for="<?php echo esc_attr($this->field_id('logo_url')); ?>"><?php esc_attr_e('Logo URL', 'mycred'); ?></label>
                        <input type="text" name="<?php echo esc_attr($this->field_name('logo_url')); ?>" id="<?php echo esc_attr($this->field_id('logo_url')); ?>" value="<?php echo esc_attr($prefs['logo_url']); ?>" class="form-control" />
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                    <h3><?php _e('Setup', 'mycred'); ?></h3>
                    <div class="form-group">
                        <label for="<?php echo esc_attr($this->field_id('currency')); ?>"><?php esc_attr_e('Currency', 'mycred'); ?></label>

                        <?php $this->currencies_dropdown('currency', 'mycred-gateway-paypal-standard-currency'); ?>

                    </div>
                    <div class="form-group">
                        <label><?php esc_attr_e('Exchange Rates', 'mycred'); ?></label>

                        <?php $this->exchange_rate_setup(); ?>

                    </div>
                </div>
            </div>
    <?php

        }

        /**
         * Sanatize Prefs
         * @since 0.1
         * @version 1.3
         */
        public function sanitise_preferences($data)
        {
            $new_data              = array();
            $new_data['sandbox']   = (isset($data['sandbox'])) ? 1 : 0;
            $new_data['currency']  = sanitize_text_field($data['currency']);
            $new_data['logo_url']  = sanitize_text_field($data['logo_url']);

            // If exchange is less then 1 we must start with a zero
            if (isset($data['exchange'])) {
                foreach ((array) $data['exchange'] as $type => $rate) {
                    if ($rate != 1 && in_array(substr($rate, 0, 1), array('.', ',')))
                        $data['exchange'][$type] = (float) '0' . $rate;
                }
            }
            $new_data['exchange']  = $data['exchange'];
            return $new_data;
        }
    };
