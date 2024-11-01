<?php

namespace ZJPP;

/**
 * PaymentSystem
 */
class PaymentSystem
{
    /**
     * _order_system
     *
     * @var mixed
     */
    protected $_order_system;
    /**
     * _payment_method
     *
     * @var mixed
     */
    protected $_payment_method;
    /**
     * transaction_data
     *
     * @var mixed
     */
    public $transaction_data;
    /**
     * payment_vendor
     *
     * @return PaymentVendor
     */
    function payment_vendor()
    {
        return $this->_payment_method;
    }
    /**
     * order_system
     *
     * @return OrderSystem
     */
    function order_system()
    {
        return $this->_order_system;
    }
    /**
     * gateway_name
     *
     * @return string
     */
    function gateway_name()
    {
        $order_system_id = $this->order_system()->order_system_data()['id'];
        $vendor_id = ($this->gateway_data())['vendor']['id'];
        $payment_method = $this->payment_vendor()->payment_method();
        return implode('-', [$order_system_id, $vendor_id, $payment_method]);
    }
    /**
     * gateway_name
     *
     * @return string
     */
    function gateway_title()
    {
        $order_system_title = __('ZJ Payment Pack', 'zj-payment-packs');
        $payment_title = $this->payment_vendor()->gateway_title();
        return esc_attr(implode(' ', [$order_system_title, $payment_title]));
    }
    /**
     * gateway_data
     *
     * @return array
     */
    function gateway_data()
    {
        return $this->payment_vendor()->gateway_data();
    }
    /**
     * Report if support subsciption
     *
     * @return bool
     */
    public function is_support_subscription()
    {
        return $this->payment_vendor()->is_payment_method_support_subscription();
    }
    /**
     * is_simulate_paid
     *
     * @return bool
     */
    public function is_simulate_paid()
    {
        return $this->payment_vendor()->postback_is_simulate_mode($this->transaction_data) ?? false;
    }
    /**
     * is_test_mode
     *
     * @return bool
     */
    public function is_test_mode()
    {
        return $this->payment_vendor()->is_test_mode();
    }
    /**
     * is_subscription_payment_passive_mode
     *
     * @return bool
     */
    public function is_subscription_payment_passive_mode()
    {
        return $this->payment_vendor()->is_subscription_payment_passive_mode();
    }
    /**
     * subscription_first_payment
     *
     * @param  mixed $data
     * @return bool
     */
    public function is_subscription_first_payment()
    {
        return $this->payment_vendor()->postback_subscription_first_payment($this->transaction_data) ?? true;
    }
    /**
     * start_payment_process
     *
     * @return void
     */
    function start_payment_process($order_id = null)
    {
        if (!$order_id)
            $order_id = $this->order_system()->create_order();

        if (!$this->order_system()->is_order_subscription($order_id)) {
            $total = $this->order_system()->get_order_total($order_id);
            $ret = $this->payment_vendor()->process_payment($order_id, $total);
        } else {
            $data = $this->order_system()->get_subscription_order_data($order_id);
            $subscription_id = $this->order_system()->get_subscription_id($order_id);
            $ret = $this->payment_vendor()->process_subscription($order_id, $data, $subscription_id);
        }
        return $ret;
    }
    /**
     * cancel order
     *
     * @param  mixed $order_id
     * @return bool
     */
    function cancel_order($order_id)
    {
        $merchant_trade_no = $this->order_system()->get_merchant_trade_no($order_id);
        return $this->payment_vendor()->process_cancel_subscription($merchant_trade_no);
    }
    /**
     * postback_url
     *
     * @return string
     */
    function postback_url()
    {
        if (is_callable(array($this->order_system(), 'postback_url'))) {
            $url = $this->order_system()->postback_url();
            if (isset($url) && $url != '')
                return $url;
        }
        return home_url(add_query_arg(
            [
                'action' => 'postback_handler',
                'gateway' => $this->gateway_name(),
            ],
            ''
        ));
    }
    /**
     * order_data
     *
     * @return array
     */
    function order_data()
    {
        return $this->payment_vendor()->postback_fill_order_data($this->transaction_data);
    }
    /**
     * __construct
     *
     * @param  mixed $order_system
     * @param  mixed $payment_method
     * @return void
     */
    function __construct($order_system, $payment_method)
    {
        $this->_order_system = $order_system;
        $this->_payment_method = $payment_method;
        $order_system->set_system($this);
        $payment_method->set_system($this);
        $this->order_system()->hook();
    }
}

(function () {
    add_action(
        'zjpp_postback_core',
        function ($system) {
            function filter_fields($data)
            {
                $_data = [];
                $get_fields = [
                    'period_amount',
                    // 'period_type',
                    // 'frequency',
                    // 'cycles',
                ];
                foreach ($get_fields as $field_name) {
                    $_data[$field_name] = $data[$field_name];
                }
                return $_data;
            }
            function period_to_ts($period, $base = 0)
            {
                $ts = 0;
                switch (strtoupper($period)) {
                    case 'YEAR':
                    case 'Y':
                        $ts = strtotime('+1 year', $base);
                        break;
                    case 'MONTH':
                    case 'M':
                        $ts = strtotime('+1 month', $base);
                        break;
                    case 'WEEK':
                    case 'W':
                        $ts = strtotime('+1 week', $base);
                        break;
                    case 'DAY':
                    case 'D':
                        $ts = strtotime('+1 day', $base);
                        break;
                }
                return $ts;
            }
            try {
                if ($system) {
                    do_action('zjpp_postback_start', $system);
                    $system->transaction_data = $transaction_data = $system->payment_vendor()->get_postback_data();
                    $order_id = $system->payment_vendor()->postback_order_id($transaction_data);
                    $transaction_id = $system->payment_vendor()->postback_transaction_id($transaction_data);
                    $merchant_trade_no = $system->payment_vendor()->postback_merchant_trade_no($transaction_data);
                    $system->order_system()->set_transaction_id($order_id, $transaction_id);
                    $system->order_system()->set_merchant_trade_no($order_id, $merchant_trade_no);

                    if (!$system->order_system()->is_order_subscription($order_id)) {
                        if ($system->payment_vendor()->is_successful_order($transaction_data)) {
                            $order_total = $system->order_system()->get_order_total($order_id);
                            $payment_total = $system->payment_vendor()->postback_order_total($transaction_data);
                            if (
                                !isset($order_total) ||
                                !isset($payment_total) ||
                                $order_total != $payment_total
                            ) throw new \Exception('Order amount and payment amount unmatched!');
                            if (
                                $system->is_test_mode() ||
                                (!$system->is_test_mode() && !$system->is_simulate_paid())
                            ) {
                                $system->payment_vendor()->postback_post_process($transaction_data, $order_id);
                                $system->order_system()->postback_post_process($order_id);
                            }
                            $system->payment_vendor()->postback_success_response($transaction_data, $order_id);
                        }
                    } else {
                        $order_data = $system->order_system()->get_subscription_order_data($order_id);
                        $subscription_id = $system->order_system()->get_subscription_id($order_id);
                        $payment_data = $system->payment_vendor()->postback_subscription_order_data($transaction_data);
                        if (
                            !isset($order_data) || !isset($payment_data) || filter_fields($order_data) != filter_fields($payment_data)
                        ) throw new \Exception('Subscription order information unmatched!');
                        if (
                            $system->is_test_mode() ||
                            (!$system->is_test_mode() && !$system->is_simulate_paid())
                        ) {
                            $order_data['extended_next_payment'] = period_to_ts($order_data['period_type'], $order_data['next_payment']);
                            $payment_data['transaction_success'] = $system->payment_vendor()->is_successful_order($transaction_data);
                            $system->payment_vendor()->postback_post_subscription_process($transaction_data, $order_id, $subscription_id);
                            $system->order_system()->postback_post_subscription_process($order_id, array_merge($order_data, $payment_data));
                        }
                        if ($system->payment_vendor()->is_successful_order($transaction_data))
                            $system->payment_vendor()->postback_success_response($transaction_data, $order_id);
                        else
                            throw new \Exception('Subscription failed!');
                    }
                    if (
                        $system->order_system()->is_support_invoice() &&
                        InvoiceSystem::general_settings('invoice_enable') == 'on' &&
                        InvoiceSystem::general_settings('invoice_auto') == 'on'
                    ) {
                        $invoice_system = \ZJPP\invoice_system();
                        if ($invoice_system) {
                            $invoice_info = $system->order_system()->invoice_info($order_id);
                            if (!$invoice_info) {
                                $invoice_data = $invoice_system->invoke_invoice($system, $order_id);
                                if ($invoice_data) {
                                    $system->order_system()->increase_invoice_successful_times($order_id);
                                    $system->order_system()->save_invoice_info($order_id, $invoice_data);
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $system->payment_vendor()->postback_failure_response($e);
            }
            if (is_callable(array($system->order_system(), 'postback_cleanup')))
                $system->order_system()->postback_cleanup($transaction_data, $order_id);
            if (is_callable(array($system->payment_vendor(), 'postback_redirect')))
                $system->payment_vendor()->postback_redirect($transaction_data, $order_id);
            exit;
        }
    );
    /**
     * postback_handler
     */
    add_action(
        'init',
        function () {
            if (
                !isset($_GET['action']) ||
                !isset($_GET['gateway']) ||
                $_GET['action'] !== 'postback_handler'
            ) {
                return;
            }
            $gateway = sanitize_text_field($_GET['gateway']);
            $system = \ZJPP\PaymentSystemCreator::payment_system_instance_gateway($gateway);
            do_action('zjpp_postback_core', $system);
        }
    );
})();
