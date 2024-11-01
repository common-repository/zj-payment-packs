<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/fields/html_fields.php';


class InvoiceSystem
{
    use singleton;
    use HtmlFields;

    /**
     * html_fields_section
     *
     * @return string
     */
    protected static function html_fields_section()
    {
        return 'invoice_systems';
    }
    /**
     * invoice_system_id
     *
     * @return string
     */
    public static function invoice_system_id()
    {
        return 'invoice_system';
    }
    /**
     * invoice_system_title
     *
     * @return string
     */
    public static function invoice_system_title()
    {
        return __('Default invoice system', 'zj-payment-packs');
    }
    /**
     * settings
     *
     * @return array
     */
    public static function general_settings($field = '')
    {
        $prefs = get_option('ZJPP_prefs', []);
        $data = [
            'invoice_enable' => $prefs['invoice_enable'] == 'on',
            'invoice_auto' => $prefs['invoice_auto'] == 'on',
            'invoice_disable_print' => $prefs['invoice_disable_print'] == 'on',
        ];
        return !empty($field) ? $data[$field] : $data;
    }
    /**
     * admin_fields
     *
     * @return array
     */
    public static function defs()
    {
        $_data = [
            'id' => static::invoice_system_id(),
            'title' => static::invoice_system_title(),
            'test_mode' => 'off',
            'enable_option' => 'off',
            'admin_fields' => [
                'invoice_test_mode' => [
                    'label' => __('Test Mode', 'zj-payment-packs'),
                    'type' => 'checkbox',
                ],
            ],
        ];
        return $_data;
    }

    /**
     * send_request
     *
     * @param  mixed $parameters
     * @param  mixed $ServiceURL
     * @return array
     */
    protected function send_request($parameters, $ServiceURL)
    {
        $rs = wp_remote_post($ServiceURL, array(
            'method'      => 'POST',
            'headers'     => false,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'body'        => http_build_query($parameters),
        ));

        if (is_wp_error($rs)) {
            throw new \Exception($rs->get_error_message());
        }
        return $rs['body'];
    }
    /**
     * set_error
     *
     * @param  string $msg error message
     * @return void
     */
    protected function set_error($msg)
    {
        $this->errors[] = $msg;
    }

    public function validate_user_input($valid_data, $set_error_func = null)
    {
        if (!isset($set_error_func)) {
            $set_error_func = array($this, 'set_error');
        }

        if (isset($valid_data['billing_invoice_type']) && sanitize_text_field($valid_data['billing_invoice_type']) == 'c' && sanitize_text_field($valid_data['billing_customer_identifier']) == '') {
            $set_error_func(__('請輸入統一編號'));
        }

        if (isset($valid_data['billing_invoice_type']) && sanitize_text_field($valid_data['billing_invoice_type']) == 'd' && sanitize_text_field($valid_data['billing_love_code']) == '') {
            $set_error_func(__('請輸入捐贈碼'));
        }

        if (isset($valid_data['billing_carruer_type']) && sanitize_text_field($valid_data['billing_carruer_type']) == '0' && \ZJPP\InvoiceSystem::general_settings('invoice_disable_print')) {
            $set_error_func(__('在啟用不使用紙本發票下不接受列印紙本發票'));
        }

        if (isset($valid_data['billing_carruer_type']) && sanitize_text_field($valid_data['billing_carruer_type']) == '2' && sanitize_text_field($valid_data['billing_carruer_num']) == '') {
            $set_error_func(__('請輸入自然人憑證載具編號'));
        }

        if (isset($valid_data['billing_carruer_type']) && sanitize_text_field($valid_data['billing_carruer_type']) == '3' && sanitize_text_field($valid_data['billing_carruer_num']) == '') {
            $set_error_func(__('請輸入手機條碼載具編號'));
        }

        if (isset($valid_data['billing_invoice_type']) && sanitize_text_field($valid_data['billing_invoice_type']) == 'c' && sanitize_text_field($valid_data['billing_customer_identifier']) != '') {

            if (!preg_match('/^[0-9]{8}$/', sanitize_text_field($valid_data['billing_customer_identifier']))) {
                $set_error_func(__('統一編號格式錯誤'));
            }
        }

        if (isset($valid_data['billing_invoice_type']) && sanitize_text_field($valid_data['billing_invoice_type']) == 'd' && sanitize_text_field($valid_data['billing_love_code']) != '') {
            if (!preg_match('/^([xX]{1}[0-9]{2,6}|[0-9]{3,7})$/', sanitize_text_field($valid_data['billing_love_code']))) {
                $set_error_func(__('捐贈碼格式錯誤'));
            } else {
                $love_code = sanitize_text_field($valid_data['billing_love_code']);
                $return_info = $this->check_love_code($love_code);
                if ($return_info)
                    if (!isset($return_info['RtnCode']) || $return_info['RtnCode'] != 1 || $return_info['IsExist'] == 'N') {
                        $set_error_func(__('請確認輸入的捐贈碼是否正確，或選擇其他發票開立方式(' . $return_info['RtnCode'] . ')'));
                    }
            }
        }
    }
    /**
     * get_items
     *
     * @param  PaymentSystem $system
     * @param  mixed $order_id
     * @param  number $tax_rate
     * @return array
     */
    public function get_items($system, $order_id, $tax_rate = 0.05)
    {
        return $system->order_system()->get_order_items($order_id);
    }
    /**
     * get_refund_items
     *
     * @param  PaymentSystem $system
     * @param  mixed $order_id
     * @param  number $tax_rate
     * @return array
     */
    public function get_refund_items($system, $order_id, $tax_rate = 0.05)
    {
        return $system->order_system()->get_order_refund_items($order_id);
    }
    /**
     * check_love_code
     *
     * @param  string $love_code
     * @return array|bool
     */
    public function check_love_code($love_code)
    {
        return false;
    }
    /**
     * invoice_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_number($invoice_data)
    {
        return '';
    }
    /**
     * invoice_date
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_date($invoice_data)
    {
        return '';
    }
    /**
     * allowance_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function allowance_number($invoice_data)
    {
        return '';
    }
    /**
     * invoke_invoice
     *
     * @param  mixed $system
     * @param  mixed $order_id
     * @return array
     */
    public function invoke_invoice($system, $order_id)
    {
        return [];
    }
    /**
     * issue_allowance
     *
     * @param  PaymentSystem $system
     * @param  mixed $order_id
     * @param  mixed $invoice_no
     * @param  mixed $invoice_date
     * @return array
     */
    public function issue_allowance($system, $order_id, $invoice_no, $invoice_date)
    {
        return [];
    }
    /**
     * invalid_allowance
     *
     * @param  mixed $system
     * @param  mixed $order_id
     * @param  mixed $invoice_no
     * @param  mixed $allowrance_no
     * @param  mixed $reason
     * @return void
     */
    public function invalid_allowance($system, $order_id, $invoice_no, $allowrance_no, $reason)
    {
        return [];
    }
    /**
     * revoke_invoice
     *
     * @param  mixed  $system
     * @param  mixed  $order_id
     * @return array
     */
    public function revoke_invoice($system, $order_id)
    {
        return [];
    }
    /**
     * pref
     *
     * @return array
     */
    public function prefs()
    {
        $prefs = get_option('ZJPP_prefs', []);
        $invoice_system_settings = $prefs[static::html_fields_section()][$this->invoice_system_id()];
        return $invoice_system_settings;
    }
    /**
     * init
     *
     * @return void
     */
    public function init()
    {
    }
}

add_action('wp_ajax_zjpp_gen_invoice', function () {
    $order_id = sanitize_text_field($_REQUEST['oid']);
    $gateway_id = sanitize_text_field($_REQUEST['gateway_id']);
    $system = PaymentSystemCreator::payment_system_instance_gateway($gateway_id);
    $invoice_data = invoice_system()->invoke_invoice($system, $order_id);
    if ($invoice_data) {
        $system->order_system()->increase_invoice_successful_times($order_id);
        if ($system->order_system()->get_order_refund_total($order_id) > 0) {
            $invoice_no = invoice_system()->invoice_number($invoice_data);
            $invoice_date = invoice_system()->invoice_date($invoice_data);
            $invoice_data = array_merge(
                $invoice_data,
                invoice_system()->issue_allowance(
                    $system,
                    $order_id,
                    $invoice_no,
                    $invoice_date
                ),
            );
        }
        $system->order_system()->save_invoice_info($order_id, $invoice_data);
        echo esc_html('發票開立成功!', 'zj-payment-packs');
    } else {
        echo esc_html('發票開立失敗!', 'zj-payment-packs');
    }
    exit;
});

add_action('wp_ajax_zjpp_invalid_invoice', function () {
    $order_id = sanitize_text_field($_REQUEST['oid']);
    $gateway_id = sanitize_text_field($_REQUEST['gateway_id']);
    $system = PaymentSystemCreator::payment_system_instance_gateway($gateway_id);
    $invoice_data = $system->order_system()->invoice_info($order_id);
    if ($invoice_data) {
        $invoice_no = invoice_system()->invoice_number($invoice_data);
        $allowrance_no = invoice_system()->allowance_number($invoice_data);
        if ($allowrance_no) {
            $data = invoice_system()->invalid_allowance(
                $system,
                $order_id,
                $invoice_no,
                $allowrance_no,
                __('作廢原先折讓,使用新折讓', 'zj-payment-packs')
            );
        }
    }
    if (invoice_system()->revoke_invoice($system, $order_id)) {
        $system->order_system()->save_invoice_info($order_id, null);
        echo esc_html('作廢發票成功!', 'zj-payment-packs');
    } else {
        echo esc_html('作廢發票失敗!', 'zj-payment-packs');
    }
    exit;
});
