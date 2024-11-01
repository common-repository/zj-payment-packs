<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/payment_vendors/PaymentVendor.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/neweb/NewebHelper.php';

/**
 * PaymentMethod_neweb
 */
class PaymentVendor_neweb extends \ZJPP\PaymentVendor
{
    const NEWEB_TEST_MERCHANT_ID = 'MS17361556';
    const NEWEB_TEST_HASH_KEY = 'MCmYlwSGnG1bvT4x7cKPqJSWXuQFjgXd';
    const NEWEB_TEST_HASH_IV = 'C5b72pgzdVZofYGP';

    /**
     * helper
     *
     * @var NewebHelper
     */
    private $helper;
    /**
     * tag
     *
     * @var string
     */
    static public $tag = 'neweb';
    /**
     * prepare_request_header
     *
     * @param  mixed $order_id
     * @param  mixed $total
     * @return array
     */
    public function prepare_request_header($order_id, $total)
    {
        $listener_url = $this->system()->postback_url();
        $listener_url = add_query_arg(['type' => 'mpg'], $listener_url);
        // $date = date('Y/m/d H:i:s', current_time('timestamp'));
        $user_email = wp_get_current_user()->user_email;
        $post_data = array(
            'MerchantID' => $this->prop('merchant_id'),
            'RespondType' => 'JSON', //回傳格式
            'TimeStamp' =>  time(),
            'Version' => '1.5',
            'MerchantOrderNo' => $this->generate_output_order_id($order_id),
            'Amt' => (string) round($total),
            'ItemDesc' => $this->item_name($order_id),
            // "ExpireDate" => date('Ymd', time()+intval($this->ExpireDate)*24*60*60),
            "Email" => $user_email,
            'LoginType' => '0',
            "NotifyURL" => $listener_url, //幕後
            "ReturnURL" => $this->system()->order_system()->finish_payment_url($order_id), //幕前(線上)
            "ClientBackURL" => $this->system()->order_system()->cancel_payment_url($order_id), //取消交易
            "LangType" => 'zh-Tw',
        );
        return $post_data;
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
        $post_data = $this->prepare_request_header($order_id, $total);
        if ($this->payment_method() == 'credit') {
            $post_data["CREDIT"] = 1;
        }
        $insts = [3, 6, 12, 18, 24, 30];
        foreach ($insts as $inst) {
            if ($this->payment_method() == 'credit-' . $inst) {
                $post_data['InstFlag'] = $inst;
                break;
            }
        }
        if ($this->payment_method() == 'webatm') {
            $post_data["WEBATM"] = 1;
        }
        $post_data = apply_filters('zjpp_neweb_post_data', $post_data);
        $url = $this->prefs['test_mode'] == "on" ? 'https://ccore.newebpay.com/MPG/mpg_gateway' : 'https://core.newebpay.com/MPG/mpg_gateway';
        $aes = $this->helper->create_mpg_aes_encrypt($post_data, $this->prop('hash_key'), $this->prop('hash_iv'));
        $sha256 = $this->helper->aes_sha256_str($aes, $this->prop('hash_key'), $this->prop('hash_iv'));
        $data = array(
            'MerchantID' => $this->prop('merchant_id'),
            'TradeInfo' => $aes,
            'TradeSha' => $sha256,
            'Version' => '1.4',
            'Cart_version' => 'NewebPay_MPG_OpenCart1_V1_0_0',
        );
        $this->helper->send_data($data, $url);
    }
    /**
     * get_postback_data
     *
     * @return array
     */
    public function get_postback_data()
    {
        $type = sanitize_text_field($_REQUEST['type']);
        if ('mpg' == $type) {
            if (!$this->helper()->check_sha_is_vaild_by_return_data($this->prop('hash_key'), $this->prop('hash_iv'))) {
                throw new \Exception('Invalid data');
            }
            $result = $this->helper()->create_aes_decrypt($_POST['TradeInfo'], $this->prop('hash_key'), $this->prop('hash_iv'));
        } else if ('periodic' == $_REQUEST['type']) {
            $result = $this->helper()->create_aes_decrypt($_POST['Period'], trim($this->prop('hash_key')), trim($this->prop('hash_iv')));
        } else {
            throw new \Exception('Type Error');
        }
        return $result;
    }
    /**
     * is_successful_order
     *
     * @param  mixed $data
     * @return bool
     */
    public function is_successful_order($data)
    {
        if ('00' == $data['RespondCode'] || 'SUCCESS' == $data['Status'])
            return true;
        return false;
    }
    /**
     * postback_order_id
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_order_id($data)
    {
        $order_id = sanitize_text_field($data['MerchantOrderNo']);
        return $this->parse_order_id($order_id);
    }
    /**
     * postback_order_total
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_order_total($data)
    {
        return sanitize_text_field($data['Amt']);
    }
    /**
     * postback_post_process
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_post_process($data, $order_id)
    {
        $msg = $data['Message'] . ', ';
        $msg .=  __('交易序號: ', 'zj-payment-packs') . $data['TradeNo'] . '.';
        $this->order_note($order_id, str_replace('Array', 'PaymentInfo', $msg));
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
     * gateway_data
     *
     * @return void
     */
    public static function gateway_data()
    {
        return [
            'vendor' => [
                'id' => 'neweb',
                'title' => __('Neweb', 'zj-payment-packs'),
                'icon' => '',
                'description' => __('ZJ Payment pack with Neweb.', 'zj-payment-packs'),
                'custom_data' => [
                    'live_url' => 'https://www.neweb.com.tw/',
                ],
            ],
            'default_prefs' => [
                'merchant_id' => '',
                'hash_key' => '',
                'hash_iv' => '',
            ],
            'test_prefs' => [],
            'admin_fields' => [
                'enable' => [
                    'description' => __('Enabling needs to fill merchant id, hash_key and hash_iv including test mode', 'zj-payment-packs'),
                ],
                'available_methods' => [
                    'label' => __('Payment Methods', 'zj-payment-packs'),
                    'type' => 'checkbox_list',
                    'items' => [
                        'credit' => [
                            'label' => __('Credit Card', 'zj-payment-packs'),
                        ],
                        'webatm' => [
                            'label' => __('Web ATM', 'zj-payment-packs'),
                        ],
                        'credit-3' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 3),
                        ],
                        'credit-6' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 6),
                        ],
                        'credit-12' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 12),
                        ],
                        'credit-18' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 18),
                        ],
                        'credit-24' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 24),
                        ],
                        'credit-30' => [
                            'label' => sprintf(__("Credit %s months", 'zj-payment-packs'), 30),
                        ],
                    ],
                ],
                'merchant_id' => [
                    'label' => __('Merchant ID', 'zj-payment-packs'),
                    'type' => 'text',
                    'length' => 30,
                ],
                'hash_key' => [
                    'label' => __('Hash Key', 'zj-payment-packs'),
                    'type' => 'text',
                    'length' => 30,
                ],
                'hash_iv' => [
                    'label' => __('Hash IV', 'zj-payment-packs'),
                    'type' => 'text',
                    'length' => 30,
                ],
            ],
        ];
    }
    /**
     * validate_fields
     *
     * @param  mixed $val
     * @param  mixed $name
     * @param  mixed $res
     * @return mixed
     */
    static function validate_fields($val, $name, $res)
    {
        if ($name[0] == 'payment_vendors' && $name[1] == 'neweb' && $name[2] == 'enable') {
            $val = ('on' === $res['payment_vendors']['neweb']['enable'] &&
                $res['payment_vendors']['neweb']['merchant_id'] &&
                $res['payment_vendors']['neweb']['hash_key'] &&
                $res['payment_vendors']['neweb']['hash_iv']) ||
                ('on' === $res['payment_vendors']['neweb']['enable'] && 'on' === $res['payment_vendors']['neweb']['test_mode']);
            $val = $val ? 'on' : 'off';
        }
        return $val;
    }
    /**
     * process_subscription
     *
     * @param  mixed $order_id
     * @param  array $data
     * @param  mixed $subscription_id
     * @return bool
     */
    public function process_subscription($order_id, $sub_data, $subscription_id)
    {
        return apply_filters('zjpp_neweb_process_subscription', false, $this, $order_id, $sub_data, $subscription_id);
    }
    /**
     * process_cancel_subscription
     *
     * @param  mixed $order_id
     * @return bool
     */
    public function process_cancel_subscription($transaction_id)
    {
        return apply_filters('zjpp_neweb_process_cancel_subscription', false, $this, $transaction_id);
    }
    /**
     * postback_subscription_order_data
     *
     * @param  mixed $order_id
     * @return array
     * subscription data which contains period type, period amount, frequency and cycles
     */
    public function postback_subscription_order_data($transaction_data)
    {
        return apply_filters(
            'zjpp_neweb_postback_subscription_order_data',
            parent::postback_subscription_order_data($transaction_data),
            $this,
            $transaction_data
        );
    }
    /**
     * call_remote_webapi
     *
     * @param  PaymentVendor_neweb $neweb
     * @param  string $endpoint
     * @param  array $data
     * @return bool
     */
    function call_remote_webapi($endpoint, $data)
    {
        $response = wp_remote_post(
            $endpoint,
            [
                'method' => 'POST',
                'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
                'body'   => $data,
            ],
        );
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            ZJPP()->logger->error($error_message);
        } else {
            return $response['body'];
        }
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
        $post_data = [
            'RespondType'       => 'JSON',
            'Version'           => '1.1',
            'Amt'               => $amount,
            'MerchantOrderNo'   => $this->system()->order_system()->get_merchant_trade_no($order_id),
            'TradeNo'           => $this->system()->order_system()->get_transaction_id($order_id),
            'IndexType'         => 1,
            'TimeStamp'         =>  time(),
            'TradeNo'           => '',
            'CloseType'         => 2,
        ];
        $aes = $this->helper()->create_mpg_aes_encrypt($post_data, $this->prop('hash_key'), $this->prop('hash_iv'));
        $endpoint = $this->endpoint_base() . '/API/CreditCard/Close';
        $data = [
            'MerchantID_'          => $this->prop('merchant_id'),
            'PostData_ '           => $aes,
        ];
        $result = $this->call_remote_webapi($endpoint, $data);
        if ($result) {
            $_result = json_decode($result);
            $msg = sanitize_text_field(urldecode($_result->Message));
            $this->system()->order_system()->order_note($order_id, __('Doing refunding: ', 'zj-payment-packs') . $msg);
            return 'Success' == $_result->Status;
        }
        return false;
    }
    public function postback_is_simulate_mode($transaction_data)
    {
        return false;
    }
    /**
     * postback_transaction_id
     *
     * @param  mixed $transaction_data
     * @return string
     */
    public function postback_transaction_id($transaction_data)
    {
        return $transaction_data['TradeNo'];
    }
    /**
     * postback_transaction_id
     *
     * @param  mixed $transaction_data
     * @return string
     */
    public function postback_merchant_trade_no($transaction_data)
    {
        return $transaction_data['MerchantOrderNo'];
    }
    /**
     * postback_subscription_first_payment
     *
     * @param  mixed $data
     * @return bool
     */
    public function postback_subscription_first_payment($transaction_data)
    {
        return $transaction_data[''] == 1;
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
     * is_payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_subscription()
    {
        return apply_filters('zjpp_neweb_is_payment_method_support_subscription', false, $this->payment_method);
    }
    /**
     * payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_refund()
    {
        return false;
        // return strpos($this->payment_method, 'credit') == 0;
    }
    /**
     * helper
     *
     * @return NewebHelper
     */
    public function helper()
    {
        return $this->helper;
    }
    /**
     * endpoint_base
     *
     * @return string
     */
    public function endpoint_base()
    {
        return $this->prefs['test_mode'] ? 'https://ccore.spgateway.com' : 'https://core.spgateway.com';
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct($payment_method = 'credit')
    {
        parent::__construct();
        $this->helper = new \ZJPP\NewebHelper();
        $this->payment_method = $payment_method;
    }
}

add_filter('zjpp_vendor_list', function ($vendor_list) {
    $vendor_list['neweb'] = 'PaymentVendor_neweb';
    return $vendor_list;
});
