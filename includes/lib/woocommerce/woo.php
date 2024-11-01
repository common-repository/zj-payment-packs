<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

return new class extends \WC_Payment_Gateway
{
    use embedded_payment_system;
    /**
     * is_available
     *
     * @return void
     */
    public function is_available()
    {
        return parent::is_available();
    }
    /**
     * method_default_title
     *
     * @return void
     */
    protected function method_default_title()
    {
        return esc_attr($this->system() ? $this->system()->payment_vendor()->gateway_title() : '');
    }
    /**
     * process_payment
     *
     * @param  mixed $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->add_order_note($this->method_default_title());

        // no reserved stocks for pending payment
        wc_release_stock_for_order($order);

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    /**
     * process_refund
     *
     * @param  mixed $order_id
     * @param  mixed $amount
     * @param  mixed $reason
     * @return bool
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->system()->order_system()->process_refund($order_id, $amount, $reason);
    }

    /**
     * admin_options
     *
     * @return void
     */
    public function admin_options()
    {
        return parent::admin_options();
    }

    /**
     * process_admin_options
     *
     * @return void
     */
    public function process_admin_options()
    {
        $this->init_settings();

        $post_data = $this->get_post_data();

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    if (in_array($this->get_field_key($key), array_keys($_POST))) {
                        $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                    }
                } catch (Exception $e) {
                    $this->add_error($e->getMessage());
                }
            }
        }

        $option_key = $this->get_option_key();
        do_action('woocommerce_update_option', array('id' => $option_key));
        return update_option($option_key, apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }
    /**
     * admin form fields definition
     *
     * @return array
     */
    public function form_fields()
    {
        return [
            'enabled' => [
                'title' => __('Enable/Disable', 'zj-payment-packs'),
                'label' => sprintf(__('Enable %s', 'zj-payment-packs'), $this->method_default_title()),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'zj-payment-packs'),
                'type' => 'text',
                'default' => $this->method_title,
                'description' => __('The title of the payment method shows when checkout.', 'zj-payment-packs'),
                'desc_tip' => true,
            ],
            'order_button_text' => [
                'title' => __('Order button text', 'zj-payment-packs'),
                'type' => 'text',
                'default' => __('Pay via ZJ Pyment Pack', 'zj-payment-packs'),
                'desc_tip' => true,
                'description' => __('The text on button for checkout of specified payment method.', 'zj-payment-packs'),
            ],
            'description' => [
                'title' => __('Description', 'zj-payment-packs'),
                'type' => 'text',
                'default' => $this->method_description,
                'desc_tip' => true,
                'description' => __('The description of the payment method shows when checkout.', 'zj-payment-packs'),
            ],
        ];
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        if ($this->system()) {
            $this->system()->order_system()->set_data([
                'gateway_obj' => $this,
            ]);
            $this->id = $this->system()->gateway_name();
            $this->has_fields = false;
            $this->method_title = esc_attr($this->system() ? $this->system()->payment_vendor()->gateway_title() : '');
            $this->method_description = esc_attr($this->system() ? $this->system()->gateway_data()['vendor']['description'] : '');
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->order_button_text = $this->settings['order_button_text'];
            $this->description = $this->settings['description'];
            $this->form_fields = $this->form_fields();
            if ($this->system()->payment_vendor()->is_payment_method_support_refund()) {
                $this->supports[] = 'refunds';
            }
            add_action('woocommerce_update_options_checkout', array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, function ($order_id) {
                $this->system()->start_payment_process($order_id);
            });
        }
    }
};
