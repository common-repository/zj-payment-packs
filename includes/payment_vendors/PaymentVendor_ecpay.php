<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/payment_vendors/PaymentVendor.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/ecpay/includes/ECPay.Payment.Integration.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/ecpay/includes/helpers/ECPayPaymentHelper.php';

/**
 * PaymentMethod_ecpay
 */
class PaymentVendor_ecpay extends \ZJPP\PaymentVendor
{
    const ECPAY_TEST_MERCHANT_ID = "2000132";
    const ECPAY_TEST_HASH_KEY = "5294y06JbISpM5x9";
    const ECPAY_TEST_HASH_IV = "v77hoKGq4kWxNNIS";
    const ECPAY_ITEM_DESC = "線上課程";

    /**
     * tag
     *
     * @var string
     */
    static public $tag = 'ecpay';

    /**
     * choosePayment
     *
     * @param  mixed $method
     * @return void
     */
    private function choosePayment($method)
    {
        $mapping = [
            'credit' => 'Credit',
            'credit-3' => 'Credit_3',
            'credit-6' => 'Credit_6',
            'credit-12' => 'Credit_12',
            'credit-18' => 'Credit_18',
            'credit-24' => 'Credit_24',
            'webatm' => 'WebAtm',
            'all' => 'ALL',
        ];
        return $mapping[$method];
    }
    /**
     * make_request_data
     *
     * @param  mixed $order_id
     * @param  mixed $total
     * @return array
     */
    public function make_request_data($order_id, $total)
    {
        $listener_url = $this->system()->postback_url();
        $data = array(
            'hashKey'           => $this->prop('hash_key'),
            'hashIv'            => $this->prop('hash_iv'),
            //背景通知的網址
            'returnUrl'         => $listener_url,
            'periodReturnURL'   => '',
            //付款成功後導向這個網址，類似 woo get_return_url()
            'clientBackUrl'     => $this->system()->order_system()->finish_payment_url($order_id),
            'orderResultURL'    => '',
            //商店訂單編號
            'orderId'           => $this->generate_output_order_id($order_id),
            'total'             => (string) round($total),
            'itemName'          => $this->item_name($order_id),
            'cartName'          => get_bloginfo('name'),
            'currency'          => '',
            'needExtraPaidInfo' => '',
        );
        return $data;
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
        try {
            $data = $this->make_request_data($order_id, $total);
            $data['choosePayment'] = $this->choosePayment($this->payment_method());
            $helper = $this->helper;
            $helper->setMerchantId($this->prop('merchant_id'));
            $helper->checkout($data);
        } catch (\Exception $e) {
            ZJPP()->logger()->error($e->getMessage());
        }
    }
    /**
     * process_subscription
     *
     * @param  mixed $order_id
     * @param  array $data
     * @param  array $subscription_id
     * @return bool
     */
    public function process_subscription($order_id, $sub_data, $subscription_id)
    {
        apply_filters('zjpp_ecpay_process_subscription', false, $this, $order_id, $sub_data, $subscription_id);
    }
    /**
     * process_cancel_subscription
     *
     * @param  mixed $order_id
     * @return bool
     */
    public function process_cancel_subscription($transaction_id)
    {
        return apply_filters('zjpp_ecpay_process_cancel_subscription', false, $this, $transaction_id);
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
            'zjpp_ecpay_postback_subscription_order_data',
            parent::postback_subscription_order_data($transaction_data),
            $this,
            $transaction_data
        );
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
    public function postback_is_simulate_mode($transaction_data)
    {
        return $transaction_data['SimulatePaid'];
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
        return $transaction_data['MerchantTradeNo'];
    }
    /**
     * postback_subscription_first_payment
     *
     * @param  mixed $data
     * @return bool
     */
    public function postback_subscription_first_payment($transaction_data)
    {
        return $transaction_data['ExecTimes'] == 1;
    }
    /**
     * get_postback_data
     *
     * @return void
     */
    public function get_postback_data()
    {
        $helper = $this->helper;
        $helper->setMerchantId($this->prop('merchant_id'));
        $arFeedback = $helper->getFeedback(
            array(
                'hashKey' => $this->prop('hash_key'),
                'hashIv' => $this->prop('hash_iv'),
            )
        );
        return $arFeedback;
    }
    /**
     * is_successful_order
     *
     * @param  mixed $data
     * @return bool
     */
    public function is_successful_order($data)
    {
        if (array_key_exists('RtnCode', $data)) {
            if ($data['RtnCode'] == '1') {
                return true;
            }
        }
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
        $helper = $this->helper;
        $payment_id = $helper->getMerchantTradeNo($data['MerchantTradeNo']);
        $payment_id = $this->parse_order_id($payment_id);
        return $payment_id;
    }
    /**
     * postback_order_total
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_order_total($data)
    {
        return $data['TradeAmt'];
    }
    /**
     * postback_post_process
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_post_process($data, $order_id)
    {
        $msg = $data['RtnMsg'] . ', ';
        $msg .= '交易日期: ' . $data['TradeDate'] . ', ';
        $msg .= '交易號碼: ' . $data['TradeNo'] . ', ';
        $msg .= '廠商訂單號碼: ' . $data['MerchantTradeNo'] . '.';
        $this->order_note($order_id, str_replace('Array', 'PaymentInfo', esc_html($msg)));
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
        echo esc_html("1|OK");
    }
    /**
     * postback_failure_response
     *
     * @param  mixed $e
     * @return void
     */
    public function postback_failure_response(\Exception $e)
    {
        echo esc_html("0|ERROR");
    }
    /**
     * gateway_data
     *
     * @return void
     */
    public static function gateway_data()
    {
        $data = [
            'vendor' => [
                'id' => 'ecpay',
                'title' => __('ECPay', 'zj-payment-packs'),
                'icon' => '',
                'description' => __('ZJ Payment pack with ECpay', 'zj-payment-packs'),
                'custom_data' => [],
            ],
            'default_prefs' => [
                'item_desc' => self::ECPAY_ITEM_DESC,
            ],
            'test_prefs' => [
                'merchant_id' => self::ECPAY_TEST_MERCHANT_ID,
                'hash_key' => self::ECPAY_TEST_HASH_KEY,
                'hash_iv' => self::ECPAY_TEST_HASH_IV,
            ],
            'admin_fields' => [
                'enable' => [
                    'description' => __('Enabling needs to fill merchant id, hash_key and hash_iv unless using test mode', 'zj-payment-packs'),
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
        return apply_filters('zjpp_ecpay_gateway_data', $data);
    }
    /**
     * validate_fields
     *
     * @param  mixed $val
     * @param  mixed $name
     * @param  mixed $res
     * @return mixed
     */
    public static function validate_fields($val, $name, $res)
    {
        if ($name[0] == 'payment_vendors' && $name[1] == 'ecpay' && $name[2] == 'enable') {
            $val = ('on' === $res['payment_vendors']['ecpay']['enable'] &&
                $res['payment_vendors']['ecpay']['merchant_id'] &&
                $res['payment_vendors']['ecpay']['hash_key'] &&
                $res['payment_vendors']['ecpay']['hash_iv']) ||
                ('on' === $res['payment_vendors']['ecpay']['enable'] &&
                    'on' === $res['payment_vendors']['ecpay']['test_mode']);
            $val = $val ? 'on' : 'off';
        }
        return $val;
    }
    /**
     * is_payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_subscription()
    {
        return apply_filters('zjpp_ecpay_is_payment_method_support_subscription', false, $this->payment_method);
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
     * endpoint_base
     *
     * @return string
     */
    public function endpoint_base()
    {
        return $this->prefs['test_mode'] == 'on' ? 'https://payment-stage.ecpay.com.tw' : 'https://payment.ecpay.com.tw';
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct($payment_method = 'credit')
    {
        parent::__construct();
        $this->helper = \ZJPP\ECPayPaymentHelper::instance();
        $this->payment_method = $payment_method;
    }
}

add_filter('zjpp_vendor_list', function ($vendor_list) {
    $vendor_list[] = 'PaymentVendor_ecpay';
    return $vendor_list;
});
