<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/order_systems/OrderSystem.php';

/**
 * OrderSystem_buycred
 */
class OrderSystem_buycred extends \ZJPP\OrderSystem
{
    /**
     * get_system
     *
     * @return void
     */
    public function get_system()
    {
        return $this->system();
    }
    /**
     * meet_requirement
     *
     * @return void
     */
    public static function meet_requirement()
    {
        return function_exists(('mycred_core'));
    }
    /**
     * finish_payment_url
     *
     * @return void
     */
    public function finish_payment_url($order_id = null)
    {
        $obj = $this->data('gateway_obj');
        return add_query_arg(['tx' => $obj->transaction_id], $obj->get_thankyou());
    }
    /**
     * cancel_payment_url
     *
     * @return string
     */
    public function cancel_payment_url($order_id = null)
    {
        return buycred_get_cancel_transaction_url();
        // return $this->finish_payment_url($order_id);
    }
    /**
     * postback_url
     *
     * @return void
     */
    public function postback_url()
    {
        $obj = $this->data('gateway_obj');
        return $obj->callback_url();
    }
    /**
     * order_system_data
     *
     * @return array
     */
    public static function order_system_data(): array
    {
        return [
            'id' => 'buycred',
            'title' => __('buyCred of myCred', 'zj-payment-packs'),
            'default_prefs' => [
                'order_prefix' => 'BUYC',
            ],
            'admin_fields' => [],
        ];
    }
    /**
     * hook
     *
     * @return void
     */
    public function hook()
    {
        add_filter('mycred_setup_gateways', function ($installed) {
            $mycred_gateway = require ZJPP_PLUGIN_DIR . '/includes/lib/buycred/gateway_buycred.php';
            $mycred_gateway->set_system($this->system());
            $installed[$this->system()->gateway_name()] = array(
                'title'         => $this->system()->gateway_title(),
                'callback'      => array(get_class($mycred_gateway)),
                'documentation' => '',
                'icon'          => 'dashicons-admin-generic',
                'sandbox'       => true,
                'external'      => true,
                'custom_rate'   => true
            );
            return $installed;
        });
        add_filter('mycred_buycred_log_refs', function ($references, $point_type) {
            if ($this->system()) {
                $obj = $this->data('gateway_obj');
                $references[] = 'buy_creds_with_' . str_replace(array(' ', '-'), '_', $obj->id);
            }
            return $references;
        }, 10, 2);
    }
    /**
     * create_order
     *
     * @return void
     */
    public function create_order()
    {
        $obj = $this->data('gateway_obj');
        return $obj->transaction_id;
    }
    /**
     * get_order_total
     *
     * @param  mixed $order_id
     * @return void
     */
    public function get_order_total($order_id)
    {
        $obj = $this->data('gateway_obj');
        $order = $obj->get_pending_payment($order_id);
        return $order->cost;
    }
    /**
     * postback_post_process
     *
     * @param  mixed $order_id
     * @return void
     */
    public function postback_post_process($order_id)
    {
        $obj = $this->data('gateway_obj');
        $pending_payment = $obj->get_pending_payment($order_id);
        if ($obj->complete_payment($pending_payment, $order_id))
            $obj->trash_pending_payment($order_id);
    }
    /**
     * order_note
     *
     * @param  mixed $content
     * @return void
     */
    public function order_note($order_id, $content)
    {
        buycred_add_pending_comment($order_id, $content);
    }
}

add_filter('zjpp_order_system_list', function ($order_system_list) {
    $order_system_list[] = 'OrderSystem_buycred';
    return $order_system_list;
});
