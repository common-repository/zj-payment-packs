<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/order_systems/OrderSystem.php';
require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/InvoiceCreator.php';

/**
 * OrderSystem_EDD
 */
class OrderSystem_EDD extends \ZJPP\OrderSystem
{
    /**
     * meet_requirement
     *
     * @return void
     */
    public static function meet_requirement()
    {
        return function_exists(('EDD'));
    }
    /**
     * finish_payment_url
     *
     * @return void
     */
    public function finish_payment_url($order_id = null)
    {
        $data = $this->data();
        $purchase_data = $data['purchase_data'];
        return add_query_arg(['payment_key' => $purchase_data['purchase_key']], edd_get_success_page_uri());
    }
    /**
     * cancel_payment_url
     *
     * @return string
     */
    public function cancel_payment_url($order_id = null)
    {
        $purchase_page = edd_get_option('purchase_page', '');
        $url = get_the_guid($purchase_page);
        return (isset($url) && $url) ? $url : $this->finish_payment_url($order_id);
    }
    /**
     * order_system_data
     *
     * @return array
     */
    public static function order_system_data(): array
    {
        return [
            'id' => 'edd',
            'title' => __('Easy Digital Downloads', 'zj-payment-packs'),
            'default_prefs' => [
                'order_prefix' => 'EDD',
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
        $gateway_name = $this->system()->gateway_name();
        // set hooks
        add_action('edd_' . $gateway_name . '_cc_form', function () {
            return false;
        });
        add_action('edd_gateway_' . $gateway_name, function ($purchase_data) {
            $this->set_data([
                'purchase_data' => $purchase_data,
            ]);
            $this->system()->start_payment_process();
        });
        // create gateway
        add_filter('edd_payment_gateways', function ($gateways) use ($gateway_name) {
            $gateways[$gateway_name] = array('admin_label' => $this->system()->gateway_title(), 'checkout_label' => $this->system()->gateway_title());
            return $gateways;
        });
    }
    /**
     * create_order
     *
     * @return void
     */
    public function create_order()
    {
        $data = $this->data();
        global $edd_options;
        $purchase_data = $data['purchase_data'];
        $payment_data = array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => $edd_options['currency'],
            'downloads' => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info' => $purchase_data['user_info'],
            'status' => 'pending',
            'gateway' => $this->system()->gateway_name(),
        );
        $payment_id = edd_insert_payment($payment_data);
        $payment_data['payment_id'] = $payment_id;
        $data['payment_data'] = $payment_data;
        $this->set_data($data);
        return $payment_id;
    }
    /**
     * get_order_total
     *
     * @param  mixed $order_id
     * @return void
     */
    public function get_order_total($order_id)
    {
        return edd_get_payment_amount($order_id);
    }
    /**
     * postback_post_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function postback_post_process($order_id)
    {
        edd_update_payment_status($order_id, 'publish');
        edd_empty_cart();
    }
    /**
     * order_note
     *
     * @param  mixed $content
     * @return void
     */
    public function order_note($order_id, $content)
    {
        edd_insert_payment_note($order_id, $content);
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
            $order_obj = new \EDD_Payment($order_id);
            $items      = [];
            $items_tmp = $order_obj->cart_details;

            foreach ($items_tmp as $key => $item) {
                $items[$key]['item_name'] = $item['name'];
                $items[$key]['item_quantity'] = $item['quantity'];
                $items[$key]['item_price'] = round($item['item_price']);
                $items[$key]['item_amount'] = round($item['subtotal']);
                $items[$key]['item_tax'] = round($item['tax']);
            }
            return $items;
        }
        return [];
    }
    public function set_error($msg)
    {
        edd_set_error('error', $msg);
    }
    /**
     * tax_rate
     *
     * @param  mixed $order_id
     * @return float
     */
    public function tax_rate($order_id)
    {
        return edd_get_tax_rate() * 100;
    }
    /**
     * invoice_data
     *
     * @param  mixed $payment_id
     * @return array
     */
    public function invoice_data($payment_id)
    {
        $order_obj = new \EDD_Payment($payment_id);
        $meta_data = $order_obj->get_meta('_edd_payment_meta');
        $meta_data = $meta_data['invoice_parameters'];
        $order_user_name      = $order_obj->user_info['last_name'] . $order_obj->user_info['first_name'];  // 購買人
        $order_email         = $order_obj->email;                     // EMAIL
        return [
            'billing_invoice_type' => $meta_data['billing_invoice_type'],
            'billing_customer_identifier' => $meta_data['billing_customer_identifier'],
            'billing_love_code' => $meta_data['billing_love_code'],
            'billing_carruer_type' => $meta_data['billing_carruer_type'],
            'billing_carruer_num' => $meta_data['billing_carruer_num'],
            'billing_user_name' => $order_user_name,
            'billing_user_phone' => '',
            'billing_user_email' => $order_email,
            'billing_user_address' => '',
        ];
    }
    /**
     * save_invoice_info
     *
     * @param  array $invoice_data
     * @return void
     */
    public function save_invoice_info($payment_id, $invoice_data)
    {
        $order_obj = new \EDD_Payment($payment_id);
        $data = $order_obj->get_meta('_edd_payment_meta');
        $data['invoice_data'] = $invoice_data;
        $order_obj->update_meta('_edd_payment_meta', $data);
    }
    /**
     * invoice_info
     *
     * @param  mixed $payment_id
     * @return array
     */
    public function invoice_info($payment_id)
    {
        $order_obj = new \EDD_Payment($payment_id);
        $data = $order_obj->get_meta('_edd_payment_meta');
        return $data['invoice_data'];
    }
    /**
     * invoice_user_input_form
     *
     * @return void
     */
    public function invoice_user_input_form()
    {
        add_action('edd_payment_mode_after_gateways_wrap', function () {
            wp_enqueue_script('zjpp-invoice-frontend', ZJPP_PLUGIN_URL . '/admin/assets/zjpp_invoice_frontend.js');
            include ZJPP_PLUGIN_DIR . '/includes/lib/invoice/invoice_user_input.php';
        });

        add_action('edd_checkout_error_checks', function ($a, $b) {
            invoice_system()->validate_user_input($_POST, array($this, 'set_error'));
        }, 10, 2);

        // save invoice infos into edd payment meta data
        add_filter('edd_payment_meta', function ($payment_meta, $payment_data) {
            if ('purchase' == $_REQUEST['edd_action'] && InvoiceSystem::general_settings('invoice_enable')) {
                $payment_meta['invoice_parameters'] = [
                    'billing_invoice_type' => sanitize_text_field($_POST['billing_invoice_type']),
                    'billing_customer_identifier' => sanitize_text_field($_POST['billing_customer_identifier']),
                    'billing_love_code' => sanitize_text_field($_POST['billing_love_code']),
                    'billing_carruer_type' => sanitize_text_field($_POST['billing_carruer_type']),
                    'billing_carruer_num' => sanitize_text_field($_POST['billing_carruer_num']),
                ];
                // $this->order_note()
            }
            return $payment_meta;
        }, 10, 2);
        add_action('edd_insert_payment', function ($payment_id, $payment_data) {
            if (InvoiceSystem::general_settings('invoice_enable')) {
                $billing_invoice_type = sanitize_text_field($_POST['billing_invoice_type']);
                $billing_customer_identifier = sanitize_text_field($_POST['billing_customer_identifier']);
                $billing_love_code = sanitize_text_field($_POST['billing_love_code']);
                $billing_carruer_type = sanitize_text_field($_POST['billing_carruer_type']);
                $billing_carruer_num = sanitize_text_field($_POST['billing_carruer_num']);
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
                $this->order_note($payment_id, $msg);
            }
        }, 10, 2);
    }

    /**
     * invoice_admin_ui
     *
     * @return void
     */
    public function invoice_admin_ui()
    {
        add_action('edd_view_order_details_update_before', function ($payment_id) {
            wp_register_script('jquery_blockUI', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js', array('jquery'), '2.7', true);
            wp_register_script('plugin_ecpay_invoice_admin_script', ZJPP_PLUGIN_URL . '/admin/assets/zjpp_invoice_admin.js', array('jquery'), '1.1', true);
            wp_enqueue_script('jquery_blockUI');
            wp_enqueue_script('plugin_ecpay_invoice_admin_script');

            // 判斷是否已經開過發票
            $order_obj = new \EDD_Payment($payment_id);
            $orderStatus = $order_obj->status;
            $invoiceInfo = $order_obj->payment_meta['invoice_data'];
            $gateway_id = $order_obj->gateway;
            // 判斷是否啟動模組
            if (
                $orderStatus == 'publish' &&
                $this->is_support_invoice() &&
                InvoiceSystem::general_settings('invoice_enable') == 'on'
            ) {
?>
                <script type="text/javascript">
                    let _data = '<?php echo json_encode(['payment_id' => $payment_id, 'gateway_id' => $gateway_id]); ?>';
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
                    $invoice_data = $this->invoice_info($payment_id);
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
    $order_system_list[] = 'OrderSystem_EDD';
    return $order_system_list;
});
