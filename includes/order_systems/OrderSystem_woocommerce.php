<?php

namespace ZJPP;

use Exception;

require_once ZJPP_PLUGIN_DIR . '/includes/order_systems/OrderSystem.php';

/**
 * OrderSystem_EDD
 */
class OrderSystem_woocommerce extends \ZJPP\OrderSystem
{
    /**
     * meet_requirement
     *
     * @return void
     */
    public static function meet_requirement()
    {
        return function_exists(('WC'));
    }
    /**
     * is_order_subscription
     *
     * @param  mixed $order_id
     * @return bool
     */
    public function is_order_subscription($order_id)
    {
        return apply_filters('zjpp_woocommerce_is_order_subscription', false, $order_id);
    }
    /**
     * get_subscription_order_data
     *
     * @param  mixed $order_id
     * @return array
     */
    public function get_subscription_order_data($order_id)
    {
        return apply_filters('zjpp_get_subscription_order_data', parent::get_subscription_order_data($order_id), $order_id);
    }
    /**
     * finish_payment_url
     *
     * @return string
     */
    public function finish_payment_url($order_id = null)
    {
        $order = wc_get_order($order_id);
        return $order->get_checkout_order_received_url();
    }
    /**
     * cancel_payment_url
     *
     * @return string
     */
    public function cancel_payment_url($order_id = null)
    {
        return add_query_arg(['order_id' => $order_id], wc_get_cart_url());
    }
    /**
     * order_system_data
     *
     * @return array
     */
    public static function order_system_data(): array
    {
        return [
            'id' => 'woocommerce',
            'title' => __('wooCommerce', 'zj-payment-packs'),
            'default_prefs' => [
                'order_prefix' => 'WOO',
            ],
            'admin_fields' => [
                'use_invoice' => [
                    'label' => __('開立發票', 'zj-payment-packs'),
                    'type' => 'checkbox',
                ],
            ],
        ];
    }
    /**
     * hook
     *
     * @return void
     */
    public function hook()
    {
        add_filter('woocommerce_payment_gateways', function ($methods) {
            $woo_gateway = require ZJPP_PLUGIN_DIR . '/includes/lib/woocommerce/woo.php';
            $woo_gateway->set_system($this->system());
            $methods[] = get_class($woo_gateway);
            return $methods;
        });
    }
    /**
     * create_order
     *
     * @return void
     */
    public function create_order()
    {
    }
    /**
     * get_order_total
     *
     * @param  mixed $order_id
     * @return void
     */
    public function get_order_total($order_id)
    {
        $order = wc_get_order($order_id);
        $total = 0;
        if ($order) {
            $total = (float) $order->get_total();
        }
        return $total;
    }
    /**
     * get_order_shipping_fee
     *
     * @param  mixed $order_id
     * @return float
     */
    public function get_order_shipping_fee($order_id)
    {
        $order = wc_get_order($order_id);
        return $order->get_shipping_total();
    }
    /**
     * postback_post_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function postback_post_process($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order->is_paid()) {
            $order->add_order_note(__('Payment completed', 'zj-payment-packs'));
            $order->payment_complete();
        }
        WC()->cart->empty_cart();
    }
    /**
     * postback_post_subscription_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function postback_post_subscription_process($order_id, $payment_data)
    {
        do_action('zjpp_wc_subscription_renewal', $order_id, $payment_data);
    }
    /**
     * order_note
     *
     * @param  mixed $content
     * @return void
     */
    public function order_note($order_id, $content)
    {
        $order = wc_get_order($order_id);
        if ($order)
            $order->add_order_note($content);
    }
    /**
     * get_subscription_id
     *
     * @param  mixed $order_id
     * @return string|int
     */
    public function get_subscription_id($order_id)
    {
        return apply_filters('zjpp_wc_get_subscription_id', $order_id);
    }
    /**
     * get_order_items
     *
     * @param  mixed $order_id
     * @return array
     */
    public function get_order_items($order_id = null)
    {
        if (isset($order_id) && !empty($order_id)) {
            $order_obj = wc_get_order($order_id);
            $items = [];
            $items_tmp = $order_obj->get_items();

            foreach ($items_tmp as $key => $item) {
                $data = $item->get_data();
                $product_id = $data['product_id'];
                $items[$product_id] = [
                    'item_name' => $data['name'],
                    'item_quantity' => $data['quantity'],
                    'item_price' => round($data['subtotal'] / $data['quantity']),
                    'item_amount' => round($data['subtotal']),
                    'item_tax' => round($data['subtotal_tax']),
                ];
            }
            return $items;
        }
        return [];
    }
    /**
     * get_order_refund_items
     *
     * @param  mixed $order_id
     * @return array
     */
    public function get_order_refund_items($order_id = null)
    {
        $items = [];
        if (isset($order_id) && !empty($order_id)) {
            $order_obj = wc_get_order($order_id);
            $refunds = $order_obj->get_refunds();
            if ($refunds) {
                foreach ($refunds as $refund) {
                    foreach ($refund->get_items() as $key => $item) {
                        $data = $item->get_data();
                        $product_id = $data['product_id'];
                        if (isset($items[$product_id])) {
                            $items[$product_id]['item_quantity'] += $data['quantity'];
                            $items[$product_id]['item_amount'] += $data['subtotal'];
                            $items[$product_id]['item_tax'] += $data['subtotal_tax'];
                        } else {
                            $items[$product_id] = [
                                'item_name' => $data['name'],
                                'item_quantity' => $data['quantity'],
                                'item_price' => round($data['subtotal'] / $data['quantity']),
                                'item_amount' => round($data['subtotal']),
                                'item_tax' => round($data['subtotal_tax']),
                            ];
                        }
                    }
                }
            }
        }
        return $items;
    }
    /**
     * get_order_refund_total
     *
     * @param  mixed $order_id
     * @return float
     */
    public function get_order_refund_total($order_id)
    {
        if (isset($order_id) && !empty($order_id)) {
            $order_obj = wc_get_order($order_id);
            return $order_obj->get_total_refunded();
        }
        return 0;
    }
    public function set_error($msg)
    {
        wc_add_notice($msg, 'error');
    }
    /**
     * tax_rate
     *
     * @param  mixed $order_id
     * @return float
     */
    public function tax_rate($order_id)
    {
        return 5;
    }
    /**
     * invoice_data
     *
     * @param  mixed $order_id
     * @return array
     */
    public function invoice_data($order_id)
    {
        $order_obj = wc_get_order($order_id);
        $order_user_name = $order_obj->data['billing']['last_name'] . $order_obj->data['billing']['first_name'];
        $order_email = $order_obj->data['billing']['email'];
        $address = $order_obj->data['billing']['address_1'] . $order_obj->data['billing']['address_2'];
        return [
            'billing_invoice_type' => $order_obj->get_meta('billing_invoice_type'),
            'billing_customer_identifier' => $order_obj->get_meta('billing_customer_identifier'),
            'billing_love_code' => $order_obj->get_meta('billing_love_code'),
            'billing_carruer_type' => $order_obj->get_meta('billing_carruer_type'),
            'billing_carruer_num' => $order_obj->get_meta('billing_carruer_num'),
            'billing_user_name' => $order_user_name,
            'billing_user_phone' => $order_obj->data['billing']['phone'],
            'billing_user_email' => $order_email,
            'billing_user_address' => $address,
        ];
    }
    /**
     * save_invoice_info
     *
     * @param  array $invoice_data
     * @return void
     */
    public function save_invoice_info($order_id, $invoice_data)
    {
        update_post_meta($order_id, 'invoice_data', $invoice_data);
    }
    /**
     * invoice_info
     *
     * @param  mixed $payment_id
     * @return array
     */
    public function invoice_info($order_id)
    {
        return get_post_meta($order_id, 'invoice_data', true);
    }
    /**
     * invoice_user_input_form
     *
     * @return void
     */
    public function invoice_user_input_form()
    {
        add_filter('woocommerce_checkout_fields', function ($fields) {
            wp_enqueue_script('zjpp-invoice-frontend', ZJPP_PLUGIN_URL . '/admin/assets/zjpp_invoice_frontend.js');
            $fields['billing'] = array_merge($fields['billing'], [
                'billing_carruer_type' => [
                    'type'      => 'select',
                    'label'         => '載具類別',
                    'required'      => false,
                    'priority'      => 200,
                    'options'   => [
                        '0' => '索取紙本',
                        '1' => '雲端發票(中獎寄送紙本)',
                        '2' => '自然人憑證',
                        '3' => '手機條碼'
                    ]
                ],
                'billing_invoice_type' => [
                    'type'          => 'select',
                    'label'         => '發票開立',
                    'required'      => false,
                    'priority'      => 210,
                    'options'   => [
                        'p' => '個人',
                        'c' => '公司',
                        'd' => '捐贈'
                    ]
                ],
                'billing_customer_identifier' => [
                    'type'          => 'text',
                    'label'         => '統一編號',
                    'required'      => false,
                    'priority'      => 220,
                ],
                'billing_love_code' => [
                    'type'          => 'text',
                    'label'         => '捐贈碼',
                    'desc_tip'      => true,
                    'required'      => false,
                    'priority'      => 230,
                ],
                'billing_carruer_num' => [
                    'type'          => 'text',
                    'label'         => '載具編號',
                    'required'      => false,
                    'priority'      => 240,
                ],
            ]);
            return $fields;
        });

        add_action('woocommerce_checkout_process', function () {
            invoice_system()->validate_user_input($_POST, array($this, 'set_error'));
        });
        add_action('woocommerce_checkout_order_processed', function ($order_id, $posted_data, $order) {
            if (InvoiceSystem::general_settings('invoice_enable')) {
                $billing_invoice_type = sanitize_text_field($posted_data['billing_invoice_type']);
                $billing_customer_identifier = sanitize_text_field($posted_data['billing_customer_identifier']);
                $billing_love_code = sanitize_text_field($posted_data['billing_love_code']);
                $billing_carruer_type = sanitize_text_field($posted_data['billing_carruer_type']);
                $billing_carruer_num = sanitize_text_field($posted_data['billing_carruer_num']);
                if (
                    empty($billing_invoice_type) &&
                    empty($billing_customer_identifier) &&
                    empty($billing_love_code) &&
                    empty($billing_carruer_type) &&
                    empty($billing_carruer_num)
                ) return;
                $billing_invoice_type = $billing_invoice_type ==
                    'p' ? __('B2C', 'zj-payment-packs') : ($billing_invoice_type == 'c' ? __('B2B', 'zj-payment-packs') : ($billing_invoice_type == 'd' ? __('捐贈', 'zj-payment-packs') :
                        ''
                    ));
                $billing_carruer_type =
                    $billing_carruer_type == '0' ? __('索取紙本', 'zj-payment-packs') : ($billing_carruer_type == '1' ? __('雲端發票', 'zj-payment-packs') : ($billing_carruer_type == '2' ? __('自然人憑證', 'zj-payment-packs') : ($billing_carruer_type == '3' ? __('手機條碼', 'zj-payment-packs') :
                        ''
                    )));
                $msg = __('使用者發票參數, ', 'zj-payment-packs');
                if ($billing_invoice_type)
                    $msg .= __('發票類型: ', 'zj-payment-packs') . $billing_invoice_type . ', ';
                if ($billing_customer_identifier)
                    $msg .= __('統編: ', 'zj-payment-packs') . $billing_customer_identifier . ', ';
                if ($billing_love_code)
                    $msg .= __('捐贈單位號碼: ', 'zj-payment-packs') . $billing_love_code . ', ';
                if ($billing_carruer_type)
                    $msg .= __('載具類型: ', 'zj-payment-packs') . $billing_carruer_type . ', ';
                if ($billing_carruer_num)
                    $msg .= __('載具號碼: ', 'zj-payment-packs') . $billing_carruer_num . '.';
                $this->order_note($order_id, $msg);
            }
        }, 10, 3);
        add_action('woocommerce_create_refund', function ($refund, $args) {
            // check if refund_amount equal the sum of all the returned purchase subtotal
            $refund_total = $args['amount'];
            $check_refund_total = 0;
            foreach ($refund->get_items() as $key => $item) {
                $data = $item->get_data();
                $check_refund_total += $data['subtotal'];
            }
            if (abs($refund_total) != abs($check_refund_total)) {
                throw new \Exception(__('各商品數量乘以單價總和與退費總額不符.', 'zj-payment-packs'));
            }
        }, 10, 2);
        add_action('woocommerce_order_refunded', function ($order_id, $refund_id) {
            $this->invoice_refunded_process($order_id);
        }, 10, 2);
    }

    /**
     * invoice_admin_ui
     *
     * @return void
     */
    public function invoice_admin_ui()
    {
        add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
            wp_register_script('jquery_blockUI', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js', array('jquery'), '2.7', true);
            wp_register_script('plugin_ecpay_invoice_admin_script', ZJPP_PLUGIN_URL . '/admin/assets/zjpp_invoice_admin.js', array('jquery'), '1.1', true);
            wp_enqueue_script('jquery_blockUI');
            wp_enqueue_script('plugin_ecpay_invoice_admin_script');

            // 判斷是否已經開過發票
            $orderStatus = $order->status;
            // $invoiceInfo = $order->payment_meta['invoice_data'];
            $invoiceInfo = $this->invoice_info($order->id);
            $gateway_id = $order->data['payment_method'];
            // 判斷是否啟動模組
            if (
                ($orderStatus == 'processing' || $orderStatus == 'completed') &&
                $this->is_support_invoice() &&
                InvoiceSystem::general_settings('invoice_enable') == 'on'
            ) {
?>
                <script type="text/javascript">
                    let _data = '<?php echo json_encode(['payment_id' => $order->id, 'gateway_id' => $gateway_id]); ?>';
                    var data = JSON.parse(_data);
                </script>
                <?php
                // 尚未開立發票就產生按鈕
                if (!isset($invoiceInfo) || empty($invoiceInfo)) { ?>
                    <p class="form-field form-field-wide" align="center">
                        <input class='button' type='button' id='invoice_button' onclick='send_orderid_to_gen_invoice(data.payment_id, data.gateway_id);' value="<?php esc_html_e('開立發票', 'zj-payment-packs'); ?>" />
                    </p>
                <?php
                } else {
                    $invoice_data = $this->invoice_info($order->id);
                    $invoice_no = invoice_system()->invoice_number($invoice_data);
                    $button_title = esc_html__('作廢發票 ', 'payment-packs') . esc_html($invoice_no);
                ?>
                    <p class="form-field form-field-wide" align="center">
                        <input class="button" type="button" id="invoice_button_issue_invalid" onclick='send_orderid_to_issue_invalid(data.payment_id, data.gateway_id);' value="<?php echo esc_html($button_title); ?>" />
                    </p>
<?php
                }
            }
        });
    }
}

add_filter('zjpp_order_system_list', function ($order_system_list) {
    $order_system_list[] = 'OrderSystem_woocommerce';
    return $order_system_list;
});
