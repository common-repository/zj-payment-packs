<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/fields/html_fields.php';

/**
 * OrderSystem
 */
class OrderSystem
{
    use \ZJPP\HtmlFields;

    /**
     * _data
     *
     * @var mixed
     */
    private $_data;
    /**
     * _payment_system
     *
     * @var mixed
     */
    private $_payment_system;
    /**
     * tag
     *
     * @var string
     */
    static public $tag = 'default';
    /**
     * target
     *
     * @var string
     */
    /**
     * order_prefix
     *
     * @var mixed
     */
    public $order_prefix;
    protected static $invoice_inited = false;
    /**
     * html_fields_section
     *
     * @return string
     */
    protected static function html_fields_section()
    {
        return 'order_systems';
    }
    /**
     * meet_requirement
     *
     * @return bool
     */
    public static function meet_requirement()
    {
        return true;
    }
    /**
     * set_data
     *
     * @param  mixed $data
     * @return void
     */
    public function set_data($data)
    {
        $this->_data = $data;
    }
    /**
     * data
     *
     * @return array|object
     */
    public function data($key = null)
    {
        if ($key) return $this->_data[$key];
        return $this->_data;
    }
    /**
     * set_system
     *
     * @param  mixed $system
     * @return void
     */
    public function set_system($system)
    {
        $this->_payment_system = $system;
    }
    /**
     * system
     *
     * @return PaymentSystem
     */
    protected function system()
    {
        return $this->_payment_system;
    }
    /**
     * hook
     *
     * @return void
     */
    public function hook()
    {
    }
    /**
     * is_order_subscription
     *
     * @param  mixed $order_id
     * @return bool
     */
    public function is_order_subscription($order_id)
    {
        return false;
    }
    /**
     * get_subscription_order_data
     *
     * @param  mixed $order_id
     * @return array
     */
    public function get_subscription_order_data($order_id)
    {
        $data = [
            'period_amount' => 0,
            'period_type' => 'D',
            'frequency' => 1,
            'cycles' => 12,
        ];
        return $data;
    }
    /**
     * finish_payment_url
     *
     * @return string
     */
    public function finish_payment_url($order_id = null)
    {
    }
    /**
     * cancel_payment_url
     *
     * @return string
     */
    public function cancel_payment_url($order_id = null)
    {
        return $this->finish_payment_url($order_id);
    }
    /**
     * order_system_data
     *
     * @return array
     */
    public static function order_system_data(): array
    {
        return [];
    }
    /**
     * admin_fields
     *
     * @return array
     */
    public static function defs()
    {
        $data = static::order_system_data();
        $_data = [
            'id' => $data['id'],
            'title' => $data['title'],
            'test_mode' => 'off',
            'admin_fields' => $data['admin_fields'],
        ];
        $_data['admin_fields']['order_prefix'] = [
            'label' => __('Order Prefix', 'zj-payment-packs'),
            'type' => 'text',
            'description' => __('The prefix before the order id', 'zj-payment-packs'),
        ];
        return $_data;
    }
    /**
     * load_prefs
     *
     * @return array
     */
    protected static function load_prefs()
    {
        $prefs = get_option('ZJPP_prefs', []);
        $data = static::defs();
        $system_id = $data['id'];
        $method_prefs = $prefs['order_systems'][$system_id];
        return isset($method_prefs) ? $method_prefs : $data['default_prefs'];
    }
    /**
     * create_order
     *
     * @return int
     */
    public function create_order()
    {
        return 0;
    }
    /**
     * get_order_total
     *
     * @param  mixed $order_id
     * @return float
     */
    public function get_order_total($order_id)
    {
        return 0;
    }
    /**
     * get_order_refund_total
     *
     * @param  mixed $order_id
     * @return float
     */
    public function get_order_refund_total($order_id)
    {
        return 0;
    }
    /**
     * get_order_shipping_fee
     *
     * @param  mixed $order_id
     * @return float
     */
    public function get_order_shipping_fee($order_id)
    {
        return 0;
    }
    /**
     * postback_post_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function postback_post_process($order_id)
    {
    }
    /**
     * postback_post_subscription_process
     *
     * @param  mixed $order_id
     * @param  mixed $payment_data
     * @return void
     */
    public function postback_post_subscription_process($order_id, $payment_data)
    {
    }
    /**
     * get_subscription_id
     *
     * @param  mixed $order_id
     * @return string|int
     */
    public function get_subscription_id($order_id)
    {
        return $order_id;
    }
    /**
     * postback_save_transaction_id
     *
     * @param  mixed $order_id
     * @param  mixed $transaction_id
     * @return void
     */
    public function set_transaction_id($order_id, $transaction_id)
    {
        update_post_meta($order_id, 'zjpp_transaction_id', $transaction_id);
    }
    /**
     * postback_save_transaction_id
     *
     * @param  mixed $order_id
     * @param  mixed $transaction_id
     * @return string
     */
    public function get_transaction_id($order_id)
    {
        return get_post_meta($order_id, 'zjpp_transaction_id', true);
    }
    /**
     * postback_save_transaction_id
     *
     * @param  mixed $order_id
     * @param  mixed $transaction_id
     * @return void
     */
    public function set_merchant_trade_no($order_id, $merchant_order_id)
    {
        update_post_meta($order_id, 'zjpp_merchant_trade_no', $merchant_order_id);
    }
    /**
     * postback_save_transaction_id
     *
     * @param  mixed $order_id
     * @param  mixed $transaction_id
     * @return string
     */
    public function get_merchant_trade_no($order_id)
    {
        return get_post_meta($order_id, 'zjpp_merchant_trade_no', true);
    }

    /**
     * order_note
     *
     * @param  mixed $content
     * @return void
     */
    public function order_note($order_id, $content)
    {
    }
    /**
     * process_refund
     *
     * @return bool
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        if ($this->system()->payment_vendor()->is_payment_method_support_refund()) {
            return $this->system()->payment_vendor()->process_refund($order_id, $amount, $reason);
        }
        return false;
    }
    /**
     * refunded_invoice_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function invoice_refunded_process($order_id)
    {
        $invoice_info = $this->invoice_info($order_id);
        if ($invoice_info) {
            $invoice_no = invoice_system()->invoice_number($invoice_info);
            $allowance_number = invoice_system()->allowance_number($invoice_info);
            if ($allowance_number) {
                if (!invoice_system()->invalid_allowance(
                    $this->system(),
                    $order_id,
                    $invoice_no,
                    $allowance_number,
                    __('作廢原先折讓,使用新折讓', 'zj-payment-packs')
                )) {
                    return false;
                }
            }
            $invoice_date = invoice_system()->invoice_date($invoice_info);
            $return_info = invoice_system()->issue_allowance($this->system(), $order_id, $invoice_no, $invoice_date);
            $this->save_invoice_info($order_id, array_merge($invoice_info, $return_info));
        }
    }
    /**
     * validate_fields
     *
     * @param  mixed $value
     * @param  mixed $name_list
     * @param  mixed $prefs
     * @return string
     */
    public static function validate_fields($value, $name_list, $prefs)
    {
        // filter with [a-zA-Z0-9]
        if ($name_list[0] == 'order_systems' && 'order_prefix' === $name_list[2]) {
            $result = preg_replace("/[^a-zA-Z0-9]+/", "", $value);
            return substr($result, 0, 10);
        }
        return $value;
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
     * is_support_invoice
     *
     * @return bool
     */
    public function is_support_invoice()
    {
        return $this->prefs['use_invoice'] == 'on';
    }
    /**
     * get_order_items
     *
     * @param  mixed $order_id
     * @return array
     */
    public function get_order_items($order_id = null)
    {
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
        return [];
    }
    /**
     * invoice_data
     *
     * @param  mixed $payment_id
     * @return array
     */
    public function invoice_data($payment_id)
    {
        return [];
    }
    /**
     * save_invoice_info
     * @param  mixed $order_id
     * @param  array $invoice_data
     * @return void
     */
    public function save_invoice_info($order_id, $invoice_data)
    {
    }
    /**
     * invoice_successful_times
     *
     * @param  mixed $order_id
     * @return string
     */
    public function invoice_successful_times($order_id)
    {
        $times = get_post_meta($order_id, 'zjpp_invoice_successful_times', true);
        if (empty($times))
            $times = 1;
        return $times;
    }
    /**
     * increase_invoice_successful_times
     *
     * @param  mixed $order_id
     * @param  mixed $times
     * @return void
     */
    public function increase_invoice_successful_times($order_id)
    {
        $times = $this->invoice_successful_times($order_id);
        update_post_meta($order_id, 'zjpp_invoice_successful_times', $times + 1);
    }
    /**
     * invoice_info
     *
     * @param  mixed $order_id
     * @return array
     */
    public function invoice_info($order_id)
    {
    }
    /**
     * invoice_user_input_form
     *
     * @return void
     */
    public function invoice_user_input_form()
    {
    }
    /**
     * invoice_admin_ui
     *
     * @return void
     */
    public function invoice_admin_ui()
    {
    }
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->prefs = static::load_prefs();
        $this->order_prefix = $this->prefs['order_prefix'];
        if (
            InvoiceSystem::general_settings('invoice_enable') &&
            $this->is_support_invoice() &&
            !self::$invoice_inited
        ) {
            self::$invoice_inited = true;
            $this->invoice_user_input_form();
            $this->invoice_admin_ui();
        }
    }
}
