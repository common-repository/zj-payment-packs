<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/InvoiceSystem.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/ecpay/traits.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

class InvoiceSystem_ecpay extends InvoiceSystem
{
    use ecpay_check_mac_value;
    use remote_post;
    use ecpay_aes;
    use ecpay_check_mac_value;

    /**
     * invoice_system_id
     *
     * @return string
     */
    public static function invoice_system_id()
    {
        return 'ecpay';
    }
    public static function invoice_system_title()
    {
        return __('綠界發票', 'zj-payment-packs');
    }
    /**
     * endpoint_base
     *
     * @return string
     */
    protected function request_url($request = '/')
    {
        $endpoint_base = $this->test_mode ? 'https://einvoice-stage.ecpay.com.tw' : 'https://einvoice.ecpay.com.tw';
        return $endpoint_base . $request;
    }
    /**
     * admin_fields
     *
     * @return array
     */
    public static function defs()
    {
        return array_merge(
            parent::defs(),
            [
                'admin_fields' => [
                    'invoice_ecpay_test_mode' => [
                        'label' => __('Test Mode', 'zj-payment-packs'),
                        'type' => 'checkbox',
                    ],
                    'invoice_ecpay_merchant_id' => [
                        'label' => __('merchant ID:', 'zj-payment-packs'),
                        'type' => 'text',
                    ],
                    'invoice_ecpay_hash_key' => [
                        'label' => __('Hash Key:', 'zj-payment-packs'),
                        'type' => 'text',
                    ],
                    'invoice_ecpay_hash_iv' => [
                        'label' => __('Hash IV:', 'zj-payment-packs'),
                        'type' => 'text',
                    ],
                ],
            ]
        );
    }
    /**
     * love_code_check_data
     *
     * @param  mixed $love_code
     * @return array
     */
    protected function love_code_check_data($love_code)
    {
        $header = [
            'LoveCode' => $love_code,
            'MerchantID' => $this->merchant_id,
            'TimeStamp' => time(),
        ];
        $header['CheckMacValue'] = $this->generate_check_mac_value($header, $this->hash_key, $this->hash_iv, true);
        return $header;
    }
    /**
     * check_love_code
     *
     * @param  string $love_code
     * @return array|bool
     */
    public function check_love_code($love_code)
    {
        try {
            $invoice_url = $this->request_url('/Query/CheckLoveCode');
            $send_data = $this->love_code_check_data($love_code);
            $return_info = $this->send_post_request($send_data, $invoice_url, false);
            parse_str($return_info, $result);
            if ($result['RtnCode'] != '1') {
                throw new \Exception($result['RtnMsg']);
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            ZJPP()->logger()->error($msg);
            return false;
        }
        return $result;
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
        $original_items = $system->order_system()->get_order_items($order_id);
        foreach ($original_items as $key => $item) {
            $items[] = [
                'ItemSeq' => $key,
                'ItemName' => $item['item_name'],
                'ItemCount' => $item['item_quantity'],
                'ItemWord' => __('個', 'zj_payment_packs'),
                'ItemPrice' => round($item['item_price']),
                'ItemAmount' => round($item['item_price'] * $item['item_quantity']),
                'ItemTaxType' => 1,
            ];
        }
        $shipping_fee = $system->order_system()->get_order_shipping_fee($order_id);
        if ($shipping_fee > 0) {
            $items[] = [
                'ItemName' => __('運費', 'zj_payment_packs'),
                'ItemCount' => 1,
                'ItemWord' => __('次', 'zj_payment_packs'),
                'ItemPrice' => $shipping_fee,
                'ItemAmount' => $shipping_fee,
                'ItemTaxType' => 1,
            ];
        }
        return $items;
    }
    /**
     * invoice_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_number($invoice_data)
    {
        return $invoice_data['InvoiceNo'];
    }
    /**
     * invoice_date
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_date($invoice_data)
    {
        return $invoice_data['InvoiceDate'];
    }
    /**
     * allowance_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function allowance_number($invoice_data)
    {
        return $invoice_data['IA_Allow_No'];
    }
    /**
     * invoke_invoice_header
     *
     * @param  PaymentSystem $system
     * @param  mixed $order_id
     * @return void
     */
    protected function invoke_invoice_header($system, $order_id)
    {
        $meta_data = $system->order_system()->invoice_data($order_id);
        $total = $system->order_system()->get_order_total($order_id);
        $generated_order_id = $system->payment_vendor()->generate_output_order_id($order_id);
        $data = [
            'MerchantID' => $this->merchant_id,
            'RelateNumber' => $generated_order_id,
            'CustomerID' => '',
            'CustomerIdentifier' => $meta_data['billing_customer_identifier'],
            'CustomerName' => $meta_data['billing_user_name'],
            'CustomerAddr' => !empty($meta_data['billing_user_address']) ? $meta_data['billing_user_address'] : 'No. 1000, St. GuangGuang, New Taipei City, Taiwan',
            'CustomerPhone' => !empty($meta_data['billing_user_phone']) ? $meta_data['billing_user_phone'] : '0911111111',
            'CustomerEmail' => $meta_data['billing_user_email'],
            // 'ClearanceMark' => 1,
            'Print' => empty($meta_data['billing_carruer_type']) && empty($meta_data['billing_love_code']) ? 1 : 0,
            'Donation' => ($meta_data['billing_invoice_type'] == 'd') ? 1 : 0,
            'LoveCode' => $meta_data['billing_love_code'],
            'CarruerType' => $meta_data['billing_carruer_type'],
            'CarruerNum' => $meta_data['billing_carruer_num'],
            'TaxType' => 1,
            'SpecialTaxType' => 0,
            'SalesAmount' => $total,
            'InvoiceRemark' => '',
            'Items' => $this->get_items($system, $order_id),
            'InvType' => '07',
            'vat' => 1,
        ];
        $invoke_invoice = [
            'MerchantID' => $this->merchant_id,
            'RqHeader' => [
                'Timestamp' => time(),
                'Revision' => '3.6.0',
            ],
            'Data' => $this->encrypt_data($data),
        ];
        return $invoke_invoice;
    }
    /**
     * invoke_invoice
     *
     * @param  mixed $system
     * @param  mixed $order_id
     * @param  mixed $items
     * @return array
     */
    public function invoke_invoice($system, $order_id)
    {
        try {
            $invoice_url = $this->request_url('/B2CInvoice/Issue');
            $send_data = $this->invoke_invoice_header($system, $order_id);
            $return_info = $this->send_post_request($send_data, $invoice_url);
            if ($return_info['TransCode'] != '1') {
                throw new \Exception($return_info['TransMsg']);
            } else {
                $return_info = $this->decrypt_data($return_info['Data']);
                if ($return_info['RtnCode'] != '1') {
                    throw new \Exception($return_info['RtnMsg']);
                }
            }
        } catch (\Exception $e) {
            $msg = __('發票開立錯誤: ', 'zj-payment-packs') . $e->getMessage();
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
            return false;
        }
        $msg = $return_info['RtnMsg'] . PHP_EOL;
        $msg .= __('發票號碼: ', 'zj-payment-packs') . $return_info['InvoiceNo'] . PHP_EOL;
        $msg .= __('發票日期: ', 'zj-payment-packs') . $return_info['InvoiceDate'] . PHP_EOL;
        $msg .= __('隨機碼: ', 'zj-payment-packs') . $return_info['RandomNumber'] . PHP_EOL;
        $system->order_system()->order_note($order_id, $msg);
        return $return_info;
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
        $original_items = $system->order_system()->get_order_refund_items($order_id);
        $items = [];
        foreach ($original_items as $key => $item) {
            $items[] = [
                'ItemSeq' => $key,
                'ItemName' => $item['item_name'],
                'ItemCount' => abs($item['item_quantity']),
                'ItemWord' => __('個', 'zj_payment_packs'),
                'ItemPrice' => round($item['item_price']),
                'ItemAmount' => abs(round($item['item_price'] * $item['item_quantity'])),
                'ItemTaxType' => 1,
            ];
        }
        return $items;
    }
    /**
     * allowance_header
     *
     * @param  mixed $system
     * @param  mixed $order_id
     * @return array
     */
    public function allowance_header($system, $order_id, $invoice_no, $invoice_date)
    {
        $meta_data = $system->order_system()->invoice_data($order_id);
        $total = $system->order_system()->get_order_refund_total($order_id);
        $data = [
            'MerchantID' => $this->merchant_id,
            'InvoiceNo' => $invoice_no,
            'InvoiceDate' => $invoice_date,
            'AllowanceNotify' => 'N',
            'CustomerName' => $meta_data['billing_user_name'],
            'NotifyMail' => $meta_data['billing_user_email'],
            'NotifyPhone' => !empty($meta_data['billing_user_phone']) ? $meta_data['billing_user_phone'] : '0911111111',
            'AllowanceAmount' => $total,
            'Items' => $this->get_refund_items($system, $order_id),
        ];
        $invoke_invoice = [
            'MerchantID' => $this->merchant_id,
            'RqHeader' => [
                'Timestamp' => time(),
                'Revision' => '3.6.0',
            ],
            'Data' => $this->encrypt_data($data),
        ];
        return $invoke_invoice;
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
        try {
            $invoice_url = $this->request_url('/B2CInvoice/Allowance');
            $send_data = $this->allowance_header($system, $order_id, $invoice_no, $invoice_date);
            $return_info = $this->send_post_request($send_data, $invoice_url);
            if ($return_info['TransCode'] == '1') {
                $return_info = $this->decrypt_data($return_info['Data']);
                if ($return_info['RtnCode'] != '1') {
                    throw new \Exception($return_info['RtnMsg']);
                }
            } else {
                throw new \Exception($return_info['TransMsg']);
            }
        } catch (\Exception $e) {
            $msg = __('折讓開立錯誤: ', 'zj-payment-packs') . $e->getMessage();
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
            return false;
        }
        $msg = $return_info['RtnMsg'] . PHP_EOL;
        $msg .= __('發票號碼: ', 'zj-payment-packs') . $return_info['IA_Invoice_No'] . PHP_EOL;
        $msg .= __('折讓號碼: ', 'zj-payment-packs') . $return_info['IA_Allow_No'] . PHP_EOL;
        $msg .= __('折讓時間: ', 'zj-payment-packs') . $return_info['IA_Date'] . PHP_EOL;
        $msg .= __('折讓剩餘金額: ', 'zj-payment-packs') . $return_info['IA_Remain_Allowance_Amt'] . PHP_EOL;
        $system->order_system()->order_note($order_id, $msg);
        return $return_info;
    }
    /**
     * invalid_allowance_header
     *
     * @param  mixed $system
     * @param  mixed $order_id
     * @param  mixed $invoice_no
     * @param  mixed $allowrance_no
     * @param  mixed $reason
     * @return array
     */
    public function invalid_allowance_header($system, $order_id, $invoice_no, $allowrance_no, $reason)
    {
        $data = [
            'MerchantID' => $this->merchant_id,
            'InvoiceNo' => $invoice_no,
            'AllowanceNo' => $allowrance_no,
            'Reason' => $reason,
        ];
        $invoke_invoice = [
            'MerchantID' => $this->merchant_id,
            'RqHeader' => [
                'Timestamp' => time(),
                'Revision' => '3.6.0',
            ],
            'Data' => $this->encrypt_data($data),
        ];
        return $invoke_invoice;
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
        try {
            $invoice_url = $this->request_url('/B2CInvoice/AllowanceInvalid');
            $send_data = $this->invalid_allowance_header($system, $order_id, $invoice_no, $allowrance_no, $reason);
            $return_info = $this->send_post_request($send_data, $invoice_url);
            if ($return_info['TransCode'] == '1') {
                $return_info = $this->decrypt_data($return_info['Data']);
                if ($return_info['RtnCode'] == '1' && $return_info['IA_Invoice_No'] == $invoice_no) {
                    $msg = $return_info['RtnMsg'] . PHP_EOL;
                    $msg .= __('發票號碼: ', 'zj-payment-packs') . $return_info['IA_Invoice_No'] . PHP_EOL;
                    $msg .= __('折讓號碼: ', 'zj-payment-packs') . $allowrance_no . PHP_EOL;
                    $system->order_system()->order_note($order_id, $msg);
                    return true;
                }
            } else {
                throw new \Exception($return_info['TransMsg']);
            }
        } catch (\Exception $e) {
            $msg = __('發票作廢折讓錯誤: ', 'zj-payment-packs') . $e->getMessage();
            $msg .= __('發票號碼: ', 'zj-payment-packs') . $invoice_no . PHP_EOL;
            $msg .= __('折讓號碼: ', 'zj-payment-packs') . $allowrance_no . PHP_EOL;
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
        }
        return false;
    }
    /**
     * revoke_invoice_header
     *
     * @param  mixed $invoice_no
     * @param  mixed $invoice_date
     * @param  mixed $reason
     * @return array
     */
    public function revoke_invoice_header($invoice_no, $invoice_date, $reason)
    {
        $data = [
            'MerchantID' => $this->merchant_id,
            'InvoiceNo' => $invoice_no,
            'InvoiceDate' => $invoice_date,
            'Reason' => $reason,
        ];
        $revoke_invoice = [
            'PlatformID' => '',
            'MerchantID' => $this->merchant_id,
            'RqHeader' => [
                'Timestamp' => time(),
                'Revision' => '3.6.0',
            ],
            'Data' => $this->encrypt_data($data),
        ];
        return $revoke_invoice;
    }
    /**
     * revoke_invoice
     *
     * @param  PaymentSystem $system
     * @param  mixed $order_id
     * @return array
     */
    public function revoke_invoice($system, $order_id, $reason = '')
    {
        try {
            if (empty($reason)) {
                $reason = __('客戶要求', 'zj_payment_packs');
            }
            $invoice_info = $system->order_system()->invoice_info($order_id);

            list($invoice_no, $invoice_date) = [$invoice_info['InvoiceNo'], $invoice_info['InvoiceDate']];
            $invoice_url = $this->request_url('/B2CInvoice/Invalid');
            $send_data = $this->revoke_invoice_header($invoice_no, $invoice_date, $reason);
            $return_info = $this->send_post_request($send_data, $invoice_url);
            if ($return_info['TransCode'] == '1') {
                $return_info = $this->decrypt_data($return_info['Data']);
                if ($return_info['RtnCode'] == '1' && $return_info['InvoiceNo'] == $invoice_no) {
                    $msg = $return_info['RtnMsg'] . PHP_EOL;
                    $msg .= __('發票號碼: ', 'zj-payment-packs') . $return_info['InvoiceNo'] . PHP_EOL;
                    $system->order_system()->order_note($order_id, $msg);
                    return true;
                }
                throw new \Exception($return_info['RtnMsg']);
            } else {
                throw new \Exception($return_info['TransMsg']);
            }
        } catch (\Exception $e) {
            $msg = __('發票作廢錯誤: ', 'zj-payment-packs') . $e->getMessage();
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
        }
        return false;
    }
    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $invoice_system_settings = $this->prefs();
        $this->test_mode = $invoice_system_settings['invoice_ecpay_test_mode'] == 'on';
        if ($this->test_mode) {
            $this->merchant_id = '2000132';
            $this->hash_key = 'ejCk326UnaZWKisg';
            $this->hash_iv = 'q9jcZX8Ib9LM8wYk';
        } else {
            $this->merchant_id = $invoice_system_settings['invoice_ecpay_merchant_id'];
            $this->hash_key = $invoice_system_settings['invoice_ecpay_hash_key'];
            $this->hash_iv = $invoice_system_settings['invoice_ecpay_hash_iv'];
        }
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        $this->init();
    }
}

add_filter('zjpp_invoice_system_list', function ($invoice_system_list) {
    $invoice_system_list[] = 'InvoiceSystem_ecpay';
    return $invoice_system_list;
});
