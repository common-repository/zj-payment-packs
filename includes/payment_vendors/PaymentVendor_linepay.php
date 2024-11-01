<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/payment_vendors/PaymentVendor.php';
require_once ZJPP_PLUGIN_DIR . '/includes/lib/linepay/linepay.php';


/**
 * PaymentMethod_linepay
 */
class PaymentVendor_linepay extends \ZJPP\PaymentVendor
{
    /**
     * tag
     *
     * @var string
     */
    static public $tag = 'linepay';

    /**
     * process_payment
     *
     * @param  mixed $order_id
     * @param  mixed $total
     * @return void
     */
    public function process_payment($order_id, $total, $subscribe = false)
    {
        $listener_url = $this->system()->postback_url();
        $client_url = $this->system()->order_system()->finish_payment_url($order_id);
        $cancel_url = $this->system()->order_system()->cancel_payment_url($order_id);
        $requestUri = '/v3/payments/request';
        $output_order_id = $this->generate_output_order_id($order_id);
        $post_data = $this->helper()->set_api_order($output_order_id, $total, $subscribe);

        //Set Redirect URLs
        $post_data['redirectUrls']['confirmUrl'] = $listener_url;
        $post_data['redirectUrls']['cancelUrl'] = $cancel_url;

        $response = $this->helper()->send_api_linepay($requestUri, $post_data, $this->debug, 'POST', $order_id);
        $response_message = $this->helper()->response_request_message($response);
        ZJPP()->logger()->info($response_message);
        // $response->info->transactionId;
        $redirect_url = $client_url;
        if ($response->returnCode == '0000') {
            if ($this->helper()->isSmartPhone()) {
                $redirect_url = $response->info->paymentUrl->app;
            } else {
                $redirect_url = $response->info->paymentUrl->web;
            }
        }
        wp_redirect($redirect_url);
        exit;
    }
    /**
     * get_postback_data
     *
     * @return void
     */
    public function get_postback_data()
    {
        $order_id = $this->postback_order_id();
        $transcation_id = sanitize_text_field($_GET['transactionId']);
        if (isset($order_id)) {
            $post_data = array();
            $order_amount = $this->system()->order_system()->get_order_total($order_id);
            $requestUri = '/v3/payments/' . $transcation_id . '/confirm';
            $post_data['amount'] = $order_amount;
            $post_data['currency'] = 'TWD';
            $output_order_id = $this->generate_output_order_id($order_id);
            $response = $this->helper()->send_api_linepay($requestUri, $post_data, $this->debug, 'POST', $output_order_id);
            $response_message = $this->helper()->response_confirm_message($response);
            ZJPP()->logger()->info($response_message);
            return $response;
        }
        throw new \Exception(__('No Order Id received.', 'zj-payment-packs'));
    }
    /**
     * is_successful_order
     *
     * @param  mixed $data
     * @return bool
     */
    public function is_successful_order($data)
    {
        if ($data->returnCode == '0000') {
            return true;
        }
        return false;
    }
    /**
     * postback_order_id
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_order_id($data = null)
    {
        $order_id = sanitize_text_field($_GET['orderId']);
        $order_id = $this->parse_order_id($order_id);
        return $order_id;
    }
    /**
     * postback_order_total
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_order_total($data)
    {
        return $data->info->packages[0]->amount;
    }
    /**
     * postback_post_process
     *
     * @param  mixed $data
     * @return void
     */
    public function postback_post_process($data, $order_id)
    {
        if (isset($data->info->authorizationExpireDate)) {
            $this->order_note($order_id, sprintf(__('Authorization Expire Date is %s.', 'zj-payment-packs'), $data->info->authorizationExpireDate));
        }
        $msg = $data->returnMessage . ', ';
        $msg .= __('交易序號: ', 'zj-payment-packs') . $data->info->transactionId . '.';
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
        echo esc_html($e->getMessage());
    }
    /**
     * postback_redirect
     *
     * @param  mixed $data
     * @param  mixed $order_id
     * @return void
     */
    public function postback_redirect($data, $order_id)
    {
        if (!$order_id)
            $order_id = $this->postback_order_id($data);
        $client_url = $this->system()->order_system()->finish_payment_url($order_id);
        wp_redirect($client_url);
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
                'id' => 'linepay',
                'title' => __('Line Pay', 'zj-payment-packs'),
                'icon' => '',
                'description' => __('ZJ Payment pack with Line Pay', 'zj-payment-packs'),
                'custom_data' => [
                    'api_live_url' => 'https://api-pay.line.me',
                ],
            ],
            'default_prefs' => [
                'order_prefix' => 'Order#',
            ],
            'test_prefs' => [],
            'admin_fields' => [
                'enable' => [
                    'description' => __('Enabling needs to fill API channel ID and API channel key unless using test mode, test settings also avaliable ', 'zj-payment-packs'),
                ],
                'available_methods' => [
                    'label' => __('Payment Methods', 'zj-payment-packs'),
                    'type' => 'checkbox_list',
                    'items' => [
                        'linepay' => [
                            'label' => __('Line Pay', 'zj-payment-packs'),
                        ],
                    ],
                ],
                'api_channel_id' => [
                    'label' => __('API Channel ID', 'zj-payment-packs'),
                    'type' => 'text',
                    'length' => 30,
                ],
                'api_channel_key' => [
                    'label' => __('API Channel Key', 'zj-payment-packs'),
                    'type' => 'text',
                    'length' => 30,
                ],
            ],
        ];
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
            'test_mode' => 'off',
            'admin_fields' => $data['admin_fields'],
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
        if ($name[0] == 'payment_vendors' && $name[1] == 'linepay' && $name[2] == 'enable') {
            $val = ('on' === $res['payment_vendors']['linepay']['enable'] &&
                $res['payment_vendors']['linepay']['api_channel_id'] &&
                $res['payment_vendors']['linepay']['api_channel_key']);
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
        return apply_filters('zjpp_linepay_process_subscription', false, $this, $order_id, $sub_data, $subscription_id);
    }
    /**
     * process_cancel_subscription
     *
     * @param  mixed $order_id
     * @return bool
     */
    public function process_cancel_subscription($transaction_id)
    {
        return apply_filters('zjpp_linepay_process_cancel_subscription', false, $this, $transaction_id);
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
            'zjpp_linepay_postback_subscription_order_data',
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
        if (!$amount) {
            $order = wc_get_order($order_id);
            $amount = $order->get_total();
        }
        $transaction_id = $this->system()->order_system()->get_transaction_id($order_id);
        $post_data = [
            'refundAmount' => $amount,
            // 'options' => [
            //     'extra' => [
            //         'promotionRestriction' => [
            //             'useLimit' => '',
            //             'rewardLimit' => '',
            //         ],
            //     ],
            // ],
        ];
        $requestUri = '/v3/payments/' . $transaction_id . '/refund';
        $response = $this->helper()->send_api_linepay($requestUri, $post_data, $this->debug, 'POST');
        $response_message = $this->helper()->response_confirm_message($response);
        ZJPP()->logger()->info($response_message);
        return '0000' == $response->returnCode;
    }
    /**
     * postback_is_simulate_mode
     *
     * @param  mixed $transaction_data
     * @return bool
     */
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
        return $transaction_data->info->transactionId;
    }
    /**
     * postback_transaction_id
     *
     * @param  mixed $transaction_data
     * @return string
     */
    public function postback_merchant_trade_no($transaction_data)
    {
        return $transaction_data->info->orderId;
    }
    /**
     * postback_subscription_first_payment
     *
     * @param  mixed $data
     * @return bool
     */
    public function postback_subscription_first_payment($transaction_data)
    {
        return isset($transaction_data->info->regKey) && $transaction_data->info->regKey;
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
        do_action('zjpp_linepay_postback_post_subscription_process', $this, $transaction_data, $order_id, $subscription_id);
    }
    /**
     * is_payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_subscription()
    {
        return apply_filters('zjpp_linepay_is_payment_method_support_subscription', false, $this->payment_method);
    }
    /**
     * payment_method_support_subscription
     *
     * @return bool
     */
    public function is_payment_method_support_refund()
    {
        return $this->payment_method === 'linepay';;
    }
    /**
     * is_subscription_payment_passive_mode
     *
     * @return bool
     */
    public function is_subscription_payment_passive_mode()
    {
        return apply_filters('zjpp_linepay_is_subscription_payment_passive_mode', true);
    }
    /**
     * helper
     *
     * @return LibLinepay
     */
    public function helper()
    {
        return \ZJPP\LibLinepay::instance();
    }
    /**
     * __construct
     *
     * @return void
     */
    function __construct($payment_method = '')
    {
        parent::__construct();
        \ZJPP\LibLinepay::$api_channel_id = $this->prefs['api_channel_id'];
        \ZJPP\LibLinepay::$api_channel_secret_key = $this->prefs['api_channel_key'];
        \ZJPP\LibLinepay::$api_endpoint_production = $this->gateway_data()['vendor']['custom_data']['api_live_url'];
        $this->payment_method = $payment_method;
    }
}

add_filter('zjpp_vendor_list', function ($vendor_list) {
    $vendor_list['linepay'] = 'PaymentVendor_linepay';
    return $vendor_list;
});
