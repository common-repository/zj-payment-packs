<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/fields/html_fields.php';

/**
 * PaymentVendor
 */
class PaymentVendor
{
    use \ZJPP\HtmlFields;

    /**
     * tag
     *
     * @var string
     */
    static public $tag = 'default';
    /**
     * _instance
     *
     * @var array
     */
    static private $_instance = [];
    /**
     * _system
     *
     * @var mixed
     */
    private $_system;
    /**
     * prefs
     *
     * @var array
     */
    protected $prefs = [];
    /**
     * payment_method
     *
     * @var string
     */
    protected $payment_method;
    /**
     * html_fields_section
     *
     * @return string
     */
    protected static function html_fields_section()
    {
        return 'payment_vendors';
    }
    /**
     * payment_method
     *
     * @return string
     */
    public function payment_method()
    {
        return $this->payment_method;
    }
    /**
     * payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_subscription()
    {
        return false;
    }
    /**
     * payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_refund()
    {
        return false;
    }
    /**
     * item_name
     *
     * @return string
     */
    public function item_name($order_id)
    {
        if ($this->system() && $this->system()->order_system() && $this->system()->order_system()->order_system_data())
            return get_bloginfo('name') . '-' . $this->system()->order_system()->order_system_data()['title'] . '-No.' . $order_id;
        return '#' . $order_id;
    }
    /**
     * instance
     *
     * @return PaymentVendor
     */
    public static function instance(): self
    {
        $tag = static::$tag;
        if (!isset(self::$_instance[$tag])) {
            $instance = new static();
            self::$_instance[$tag] = $instance;
        }
        return self::$_instance[$tag];
    }
    /**
     * set_system
     *
     * @param  mixed $system
     * @return void
     */
    public function set_system($system)
    {
        $this->_system = $system;
    }
    /**
     * system
     *
     * @return PaymentSystem
     */
    public function system()
    {
        return $this->_system;
    }
    /**
     * gateway_data
     *
     * @return array
     */
    public static function gateway_data()
    {
        return null;
    }
    /**
     * gateway_title
     *
     * @return string
     */
    public function gateway_title()
    {
        $method = $this->payment_method();
        $vendor_title = $this->gateway_data()['vendor']['title'];
        $method_title = $this->gateway_data()['admin_fields']['available_methods']['items'][$method]['label'];
        return implode('-', [$vendor_title, $method_title]);
    }
    /**
     * admin_fields
     *
     * @return array
     */
    public static function defs()
    {
        $data = static::gateway_data();
        return [
            'id' => $data['vendor']['id'],
            'title' => $data['vendor']['title'],
            'test_mode' => 'on',
            'admin_fields' => $data['admin_fields'],
        ];
    }
    /**
     * process_payment
     *
     * @param  mixed $order_id
     * @param  mixed $total
     * @return void
     */
    public function process_payment($order_id, $total)
    {
    }
    /**
     * process_subscription
     *
     * @param  mixed $order_id
     * order id
     * @param  array $data
     * @param  mixed $subscription_id
     * subscription data
     * @return bool
     */
    public function process_subscription($order_id, $data, $subscription_id)
    {
        return false;
    }
    /**
     * process_cancel_subscription
     *
     * @param  mixed $order_id
     * 
     * @return bool
     */
    public function process_cancel_subscription($transaction_id)
    {
        return false;
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
        return false;
    }
    /**
     * postback_subscription_order_data
     *
     * @param  mixed $order_id
     * @return array
     * subscription data which contains period type, period amount, frequency and cycles
     */
    public function postback_subscription_order_data($data)
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
     * get_postback_data
     *
     * @return array
     */
    public function get_postback_data()
    {
    }
    /**
     * is_successful_order
     *
     * @param  mixed $data
     * @return bool
     */
    public function is_successful_order($data)
    {
        return false;
    }
    /**
     * generate_output_order_id
     *
     * @param  mixed $order_id
     * @param  bool  $force_live
     * @return string
     */
    public function generate_output_order_id($order_id, $force_live = false)
    {
        $len = 16;
        $generate_string = function ($strength) {
            $input = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $input_length = strlen($input);
            $random_string = '';
            for ($i = 0; $i < $strength; $i++) {
                $random_character = $input[mt_rand(0, $input_length - 1)];
                $random_string .= $random_character;
            }
            return $random_string;
        };
        $order_prefix = $this->system()->order_system()->order_prefix;
        $order_id = (string) $order_id;
        $output_order_id = $order_prefix . $order_id;
        if (!$force_live && $this->is_test_mode()) {
            $before_prefix_len = $len - strlen($output_order_id);
            $len = $len - strlen($order_id);
            if ($len > 1) {
                $a = ($len < 10) ? chr(ord('0') + $len) : chr(ord('A') + $len - 10);
                $output_order_id = $a . $generate_string($before_prefix_len - 1) . $output_order_id;
            }
        }
        return $output_order_id;
    }
    /**
     * parse_order_id
     *
     * @param  mixed $order_id
     * @return string
     */
    public function parse_order_id($order_id)
    {
        if (!$this->is_test_mode()) {
            $order_prefix = $this->system()->order_system()->order_prefix;
            $_order_id = $order_id;
            if (!empty($order_prefix))
                $_order_id = explode($order_prefix, $order_id)[1];
        } else {
            $a = $order_id[0];
            $start = 0;
            if (ord('0') <= ord($a) && ord($a) <= ord('9')) $start = ord($a) - ord('0');
            if (ord('A') <= ord($a) && ord($a) <= ord('Z')) $start = ord($a) - ord('A') + 10;
            $_order_id = substr($order_id, $start);
        }
        return $_order_id;
    }
    /**
     * postback_is_simulate_mode
     *
     * @param  mixed $data
     * @return bool
     */
    public function postback_is_simulate_mode($data)
    {
        return false;
    }
    /**
     * postback_subscription_first_payment
     *
     * @param  mixed $data
     * @return bool
     */
    public function postback_subscription_first_payment($data)
    {
        return true;
    }
    /**
     * postback_order_id
     *
     * @param  mixed $data
     * @return string
     */
    public function postback_order_id($data)
    {
    }
    /**
     * postback_transaction_id
     *
     * @param  mixed $data
     * @return string
     */
    public function postback_transaction_id($data)
    {
        return '';
    }
    /**
     * postback_fill_order_data
     *
     * @param  mixed $data
     * @return array
     */
    public function postback_fill_order_data($data)
    {
        return [
            'payer_email' => '',
            'payer_phone' => '',
            'payer_address' => '',
        ];
    }
    /**
     * postback_transaction_id
     *
     * @param  mixed $transcation_data
     * @return string
     */
    public function postback_merchant_trade_no($transcation_data)
    {
        return '';
    }
    /**
     * postback_order_total
     *
     * @param  mixed $data
     * @return string
     */
    public function postback_order_total($data)
    {
    }
    /**
     * postback_post_process
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_post_process($data, $order_id)
    {
    }
    /**
     * postback_post_subscription_process
     *
     * @param  mixed $transaction_data
     * @param  string|int $order_id
     * @param  string|int $subscription_id
     * @return void
     */
    public function postback_post_subscription_process($transaction_data, $order_id, $subscription_id)
    {
    }
    /**
     * postback_success_response
     *
     * @return void
     */
    public function postback_success_response($data, $order_id)
    {
    }
    /**
     * postback_failure_response
     *
     * @param  mixed $e
     * @return void
     */
    public function postback_failure_response(\Exception $e)
    {
    }
    /**
     * prop
     *
     * @param  string $name
     * @return mixed
     */
    public function prop(string $name)
    {
        $test_prefs = $this->gateway_data()['test_prefs'];
        return ($this->is_test_mode() && $test_prefs[$name]) ? $test_prefs[$name] : $this->prefs[$name];
    }
    /**
     * order_note
     *
     * @param  mixed $content
     * @return void
     */
    public function order_note($order_id, $note)
    {
        $this->system()->order_system()->order_note($order_id, $note);
    }
    /**
     * is_test_mode
     *
     * @return bool
     */
    public function is_test_mode()
    {
        return $this->prefs['test_mode'] == 'on';
    }
    /**
     * is_subscription_payment_passive_mode
     *
     * @return bool
     */
    public function is_subscription_payment_passive_mode()
    {
        return true;
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
        $vendor_id = $data['id'];
        $method_prefs = $prefs['payment_vendors'][$vendor_id];
        return isset($method_prefs) ? $method_prefs : $data['default_prefs'];
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        $this->prefs = static::load_prefs();
    }
}
