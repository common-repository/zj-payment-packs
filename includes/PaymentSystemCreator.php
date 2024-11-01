<?php

namespace ZJPP;

/**
 * PaymentSystemCreator
 */
class PaymentSystemCreator
{
    static protected $_instance = [];
    /**
     * instance
     *
     * @param  mixed $package_name
     * @param  mixed $classname
     * @param  mixed $arguments
     * @param  mixed $tag
     * @return PaymentSystemCreator
     */
    protected static function instance($package_name, $classname, $arguments = [], $tag = null)
    {
        if (!isset($tag)) $tag = $classname;
        if (!isset(self::$_instance[$tag])) {
            $base_dir = trailingslashit(__DIR__) . $package_name . '/';
            $filename = explode('\\ZJPP\\', $classname)[1];
            require_once $base_dir . $filename . '.php';
            self::$_instance[$tag] = call_user_func_array(
                array(
                    new \ReflectionClass($classname), 'newInstance'
                ),
                $arguments
            );
        }
        return self::$_instance[$tag];
    }
    /**
     * create_order_system
     *
     * @param  mixed $classname
     * @return OrderSystem
     */
    protected static function create_order_system($classname)
    {
        $path = ZJPP_PLUGIN_DIR . '/includes/order_systems/' . explode('\\ZJPP\\', $classname)[1];
        if (file_exists($path)) {
            require_once $path;
        } else {
            do_action('zjpp_create_order_system', $classname);
        }
        return new $classname;
    }
    /**
     * create_payment_vendor
     *
     * @param  mixed $classname
     * @param  mixed $payment_method
     * @return PaymentVendor
     */
    protected static function create_payment_vendor($classname, $payment_method)
    {
        $base_dir = trailingslashit(__DIR__) . 'payment_vendors/';
        $filename = explode('\\ZJPP\\', $classname)[1];
        require_once $base_dir . $filename . '.php';
        return new $classname($payment_method);
    }
    /**
     * create_order_payment_system
     *
     * @param  mixed $order_classname
     * @param  mixed $payment_classname
     * @param  mixed $payment_method
     * @return PaymentSystem
     */
    public static function create_order_payment_system($order_classname, $payment_classname, $payment_method)
    {
        $order_system = self::create_order_system($order_classname);
        $payment_system = self::create_payment_vendor($payment_classname, $payment_method);
        $order_system_id = $order_system->order_system_data()['id'];
        $vendor_id = $payment_system->gateway_data()['vendor']['id'];
        $gateway_name = implode('-', [$order_system_id, $vendor_id, $payment_method]);

        $system = self::instance(
            '.',
            '\\ZJPP\\PaymentSystem',
            [$order_system, $payment_system],
            $gateway_name
        );
        return $system;
    }
    /**
     * payment_system_instance_gateway
     *
     * @param  mixed $gateway
     * @return PaymentSystem
     */
    public static function payment_system_instance_gateway($gateway)
    {
        return self::$_instance[$gateway];
    }
}
