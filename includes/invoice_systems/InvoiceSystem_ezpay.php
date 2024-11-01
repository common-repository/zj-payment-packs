<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/InvoiceSystem.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/invoice/traits.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/neweb/traits.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

class InvoiceSystem_ezpay extends InvoiceSystem
{
    use neweb_check_code;
    use remote_post;
    use neweb_aes;

    /**
     * invoice_system_id
     *
     * @return string
     */
    public static function invoice_system_id()
    {
        return 'ezpay';
    }
    public static function invoice_system_title()
    {
        return __('ezPay發票', 'zj-payment-packs');
    }
    /**
     * endpoint_base
     *
     * @return string
     */
    protected function request_url($request = '/')
    {
        $endpoint_base = $this->test_mode ? 'https://cinv.ezpay.com.tw' : 'https://inv.ezpay.com.tw';
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
                    'invoice_ezpay_test_mode' => [
                        'label' => __('Test Mode', 'zj-payment-packs'),
                        'type' => 'checkbox',
                    ],
                    'invoice_ezpay_merchant_id' => [
                        'label' => __('merchant ID:', 'zj-payment-packs'),
                        'type' => 'text',
                    ],
                    'invoice_ezpay_hash_key' => [
                        'label' => __('Hash Key:', 'zj-payment-packs'),
                        'type' => 'text',
                    ],
                    'invoice_ezpay_hash_iv' => [
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
        return [];
    }
    /**
     * check_love_code
     *
     * @param  string $love_code
     * @return array|bool
     */
    public function check_love_code($love_code)
    {
        return [];
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
        $items = [];
        foreach ($original_items as $key => $item) {
            $items['ItemNames'][] = substr($item['item_name'], 0, 30);
            $items['ItemCounts'][] = $item['item_quantity'];
            $items['ItemWords'][] = __('個', 'zj_payment_packs');
            $items['ItemPrices'][] = round($item['item_price']);
            $items['ItemAmount'][] = round($item['item_price'] * $item['item_quantity']);
            $items['ItemTax'][] = round($item['item_tax']);
            $items['ItemTaxType'][] = 1;
        }
        $shipping_fee = $system->order_system()->get_order_shipping_fee($order_id);
        if ($shipping_fee > 0) {
            $items['ItemNames'][] = __('運費', 'zj_payment_packs');
            $items['ItemCounts'][] = 1;
            $items['ItemWords'][] = __('次', 'zj_payment_packs');
            $items['ItemPrices'][] = $shipping_fee;
            $items['ItemAmount'][] = $shipping_fee;
            $items['ItemTax'][] = 0;
            $items['ItemTaxType'][] = 1;
        }
        $item_name = implode('|', $items['ItemNames']);
        $item_counts = implode('|', $items['ItemCounts']);
        $item_units = implode('|', $items['ItemWords']);
        $item_prices = implode('|', $items['ItemPrices']);
        $item_amounts = implode('|', $items['ItemAmount']);
        $item_tax = implode('|', $items['ItemTax']);
        $item_tax_type = implode('|', $items['ItemTaxType']);

        return [$item_name, $item_counts, $item_units, $item_prices, $item_amounts, $item_tax_type, $item_tax];
    }
    /**
     * invoice_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_number($invoice_data)
    {
        return $invoice_data['InvoiceNumber'];
    }
    /**
     * invoice_date
     *
     * @param  array $invoice_data
     * @return string
     */
    public function invoice_date($invoice_data)
    {
        return $invoice_data['CreateTime'];
    }
    /**
     * allowance_number
     *
     * @param  array $invoice_data
     * @return string
     */
    public function allowance_number($invoice_data)
    {
        return $invoice_data['AllowanceNo'];
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
        $category = empty($meta_data['billing_customer_identifier']) ? 'B2C' : 'B2B';
        $type_mapping = [
            '1' => '2',
            '2' => '1',
            '3' => '0',
        ];
        $carrier_type = $type_mapping[$meta_data['billing_carruer_type']];
        $carrier_type = !empty($meta_data['billing_carruer_num']) ? $carrier_type : '';
        $print_flag = $category == 'B2B' ? 'Y' : (!is_numeric($carrier_type) && empty($meta_data['billing_love_code']) ? 'Y' : 'N');
        $tax_rate = $system->order_system()->tax_rate($order_id);
        $generated_order_id = $system->order_system()->get_merchant_trade_no($order_id) . $system->order_system()->invoice_successful_times($order_id);
        list($item_name, $item_counts, $item_units, $item_prices, $item_amounts, $item_tax_type, $item_tax) = $this->get_items($system, $order_id);
        $tax_amount = 0;
        foreach (explode('|', $item_tax) as $tax) {
            $tax_amount += (int) $tax;
        }
        $item_amounts_sum = 0;
        foreach (explode('|', $item_amounts) as $amount) {
            $item_amounts_sum += (int) $amount;
        }
        $data = [
            'RespondType' => 'JSON',
            'Version' => '1.5',
            'TimeStamp' => time(),
            'TransNum' => '',
            'MerchantOrderNo' => $generated_order_id,
            'Status' => '1',
            'CreateStatusTime' => '',
            'Category' => $category,
            'BuyerName' => $meta_data['billing_user_name'],
            'BuyerUBN' => $meta_data['billing_customer_identifier'],
            'BuyerAddress' => !empty($meta_data['billing_user_address']) ? $meta_data['billing_user_address'] : 'No. 1000, St. GuangGuang, New Taipei City, Taiwan',
            'BuyerEmail' => $meta_data['billing_user_email'],
            'CarrierType' => $carrier_type,
            'CarrierNum' => $meta_data['billing_carruer_num'],
            'LoveCode' => $meta_data['billing_love_code'],
            'PrintFlag' => $print_flag,
            'KioskPrintFlag' => $carrier_type == '2' ? '1' : '',
            'TaxType' => '1',
            'TaxRate' => $tax_rate,
            'CustomsClearance' => '',
            'Amt' => $item_amounts_sum,
            'AmtSales' => '',
            'AmtZero ' => '',
            'AmtFree' => '',
            'TaxAmt' => $tax_amount,
            'TotalAmt' => $total,
            'ItemName' => $item_name,
            'ItemCount' => $item_counts,
            'ItemUnit' => $item_units,
            'ItemPrice' => $item_prices,
            'ItemAmt' => $item_amounts,
            'ItemTaxType' => $item_tax_type,
            'Comment' => '',
        ];
        $invoke_invoice = [
            'MerchantID_' => $this->merchant_id,
            'PostData_' => $this->create_mpg_aes_encrypt($data, $this->hash_key, $this->hash_iv),
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
            $invoice_url = $this->request_url('/Api/invoice_issue');
            $send_data = $this->invoke_invoice_header($system, $order_id);
            $return_info = $this->send_post_request($send_data, $invoice_url, false);
            if ($return_info['Status'] == 'SUCCESS') {
                $result = (array) json_decode($return_info['Result']);
                $chkcode = $this->get_check_code(
                    [
                        'InvoiceTransNo' => $result['InvoiceTransNo'],
                        'MerchantID' => $result['MerchantID'],
                        'MerchantOrderNo' => $result['MerchantOrderNo'],
                        'RandomNum' => $result['RandomNum'],
                        'TotalAmt' => $result['TotalAmt'],
                    ],
                    $this->hash_key,
                    $this->hash_iv
                );
                if ($result['CheckCode'] != $chkcode) {
                    throw new \Exception('CheckCode incorrect');
                }
            } else {
                throw new \Exception($return_info['Message']);
            }
        } catch (\Exception $e) {
            $msg = __('開立發票失敗: ', 'zj-payment-packs') . $e->getMessage();
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
            return false;
        }
        $msg = $return_info['Message'] . ', ';
        $msg .= __('發票號碼: ', 'zj-payment-packs') . $result['InvoiceNumber'] . ', ';
        $msg .= __('發票日期: ', 'zj-payment-packs') . $result['CreateTime'] . ', ';
        $msg .= __('隨機碼: ', 'zj-payment-packs') . $result['RandomNum'] . '.';
        $system->order_system()->order_note($order_id, $msg);
        return $result;
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
            $items['ItemNames'][] = substr($item['item_name'], 0, 30);
            $items['ItemCounts'][] = abs($item['item_quantity']);
            $items['ItemWords'][] = __('個', 'zj_payment_packs');
            $items['ItemPrices'][] = round($item['item_price']);
            $items['ItemAmount'][] = abs(round($item['item_price'] * $item['item_quantity']));
            $items['ItemTax'][] = round($item['item_tax']);
            $items['ItemTaxType'][] = 1;
        }
        $item_name = implode('|', $items['ItemNames']);
        $item_counts = implode('|', $items['ItemCounts']);
        $item_units = implode('|', $items['ItemWords']);
        $item_prices = implode('|', $items['ItemPrices']);
        $item_amounts = implode('|', $items['ItemAmount']);
        $item_tax = implode('|', $items['ItemTax']);
        $item_tax_type = implode('|', $items['ItemTaxType']);

        return [$item_name, $item_counts, $item_units, $item_prices, $item_amounts, $item_tax_type, $item_tax];
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
        $seq = $system->order_system()->invoice_successful_times($order_id) - 1;
        $generated_order_id = $system->order_system()->get_merchant_trade_no($order_id) . $seq;
        list($item_name, $item_counts, $item_units, $item_prices, $item_amounts, $item_tax_type, $item_tax) = $this->get_refund_items($system, $order_id);
        $data = [
            'RespondType' => 'JSON',
            'Version' => '1.3',
            'TimeStamp' => time(),
            'InvoiceNo' => $invoice_no,
            'MerchantOrderNo' => $generated_order_id,
            'ItemName' => $item_name,
            'ItemCount' => $item_counts,
            'ItemUnit' => $item_units,
            'ItemPrice' => $item_prices,
            'ItemAmt' => $item_amounts,
            'TaxTypeForMixed' => $item_tax_type,
            'ItemTaxAmt' => $item_tax,
            'TotalAmt' => $total,
            'BuyerEmail' => $meta_data['billing_user_email'],
            'Status' => 1,
        ];
        $invoice_allowance = [
            'MerchantID_' => $this->merchant_id,
            'PostData_' => $this->create_mpg_aes_encrypt($data, $this->hash_key, $this->hash_iv),
        ];
        return $invoice_allowance;
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
            $invoice_url = $this->request_url('/Api/allowance_issue');
            $send_data = $this->allowance_header($system, $order_id, $invoice_no, $invoice_date);
            $return_info = $this->send_post_request($send_data, $invoice_url, false);
            if ($return_info['Status'] == 'SUCCESS') {
                $result = (array) json_decode($return_info['Result']);
            } else {
                throw new \Exception($return_info['Message']);
            }
        } catch (\Exception $e) {
            $msg = __('折讓開立錯誤: ', 'zj-payment-packs') . $e->getMessage();
            ZJPP()->logger()->error($msg);
            $system->order_system()->order_note($order_id, $msg);
            return false;
        }
        $msg = $return_info['Message'] . PHP_EOL;
        $msg .= __('發票號碼: ', 'zj-payment-packs') . $result['InvoiceNumber'] . PHP_EOL;
        $msg .= __('折讓號碼: ', 'zj-payment-packs') . $result['AllowanceNo'] . PHP_EOL;
        $msg .= __('折讓金額: ', 'zj-payment-packs') . $result['AllowanceAmt'] . PHP_EOL;
        $msg .= __('折讓剩餘金額: ', 'zj-payment-packs') . $result['RemainAmt'] . PHP_EOL;
        $system->order_system()->order_note($order_id, $msg);
        return $result;
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
            'RespondType' => 'JSON',
            'Version' => '1.0',
            'TimeStamp' => time(),
            'AllowanceNo' => $allowrance_no,
            'InvalidReason' => substr($reason, 0, 6),
        ];
        $invoke_invoice = [
            'MerchantID_' => $this->merchant_id,
            'PostData_' => $this->create_mpg_aes_encrypt($data, $this->hash_key, $this->hash_iv),
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
            $invoice_url = $this->request_url('/Api/allowanceInvalid');
            $send_data = $this->invalid_allowance_header($system, $order_id, $invoice_no, $allowrance_no, $reason);
            $return_info = $this->send_post_request($send_data, $invoice_url, false);
            if ($return_info['Status'] == 'SUCCESS') {
                $result = (array) json_decode($return_info['Result']);
                if ($result['AllowanceNo'] == $allowrance_no) {
                    $msg = $return_info['Message'] . PHP_EOL;
                    $msg .= __('發票號碼: ', 'zj-payment-packs') . $invoice_no . PHP_EOL;
                    $msg .= __('折讓號碼: ', 'zj-payment-packs') . $allowrance_no . PHP_EOL;
                    $system->order_system()->order_note($order_id, $msg);
                    return true;
                }
            } else {
                throw new \Exception($return_info['Message']);
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
            'RespondType' => 'JSON',
            'Version' => '1.0',
            'TimeStamp' => time(),
            'InvoiceNumber' => $invoice_no,
            'InvalidReason' => $reason,
        ];
        $revoke_invoice = [
            'MerchantID_' => $this->merchant_id,
            'PostData_' => $this->create_mpg_aes_encrypt($data, $this->hash_key, $this->hash_iv),
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
                $reason = __('無原因', 'zj_payment_packs');
            }
            $invoice_info = $system->order_system()->invoice_info($order_id);

            list($invoice_no, $invoice_date) = [$invoice_info['InvoiceNumber'], $invoice_info['CreateTime']];
            $invoice_url = $this->request_url('/Api/invoice_invalid');
            $send_data = $this->revoke_invoice_header($invoice_no, $invoice_date, $reason);
            $return_info = $this->send_post_request($send_data, $invoice_url, false);
            if ($return_info['Status'] == 'SUCCESS') {
                $result = (array) json_decode($return_info['Result']);
                if ($result['InvoiceNumber'] == $invoice_no) {
                    $msg = $return_info['Message'] . ', ';
                    $msg .= __('發票號碼: ', 'zj-payment-packs') . $result['InvoiceNumber'] . '.';
                    $system->order_system()->order_note($order_id, $msg);
                    return true;
                }
            } else {
                throw new \Exception($return_info['Message']);
            }
        } catch (\Exception $e) {
            $msg = __('作廢發票失敗: ', 'zj-payment-packs') . $e->getMessage();
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
        $this->test_mode = $invoice_system_settings['invoice_ezpay_test_mode'] == 'on';
        $this->merchant_id = $invoice_system_settings['invoice_ezpay_merchant_id'];
        $this->hash_key = $invoice_system_settings['invoice_ezpay_hash_key'];
        $this->hash_iv = $invoice_system_settings['invoice_ezpay_hash_iv'];
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
    $invoice_system_list[] = 'InvoiceSystem_ezpay';
    return $invoice_system_list;
});
