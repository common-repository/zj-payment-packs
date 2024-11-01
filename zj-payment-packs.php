<?php

/**
 * Plugin Name:       ZJ 金流整合
 * Plugin URI:        http://demo-cj.net/zj-payment-packs/
 * Description:       Let the payment methods of ECPay, Neweb and Line Pay support to wooCommerce, easy digital downloads and mycred.
 * Version:           1.1.2
 * Author:            DS workshop
 * Author URI:        http://demo-cj.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       zj-payment-packs
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ZJPP')) {
    define('ZJPP_PLUGIN_NAME', basename(__DIR__));
    define('ZJPP_PLUGIN_DIR', __DIR__);
    define('ZJPP_PLUGIN_URL', plugins_url(ZJPP_PLUGIN_NAME));
    define('ZJPP_PLUGIN_LOG_PATH', trailingslashit(wp_get_upload_dir()['basedir']) . 'zjpp_log.txt');

    require_once ZJPP_PLUGIN_DIR . '/admin/admin.php';
    require_once ZJPP_PLUGIN_DIR . '/includes/PaymentSystemCreator.php';
    require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/InvoiceCreator.php';
    require_once ZJPP_PLUGIN_DIR . '/includes/lib/logger/logger.php';
    require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

    /**
     * ZJPP_Main
     */
    class ZJPP_Main
    {
        use \ZJPP\singleton;
        /**
         * _logger
         *
         * @var Logger
         */
        private $_logger;
        /**
         * get_plugin_basename
         *
         * @return string
         */
        protected function get_plugin_basename(): string
        {
            $_base = plugin_basename(__FILE__);
            return $_base;
        }
        /**
         * return the plugin settings url of the admin options
         *
         * @return string
         */
        protected function get_settings_url(): string
        {
            return admin_url('options-general.php?page=ZJPP_setting_fields');
        }
        /**
         * add settings url link for the plugin
         * 
         * @param  array $links
         * @return array
         */
        protected function add_plugin_links(array $links): array
        {
            // Display settings url if setup is complete otherwise link to get started page
            $_link = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_attr($this->get_settings_url()),
                esc_html__('Settings')
            );
            array_unshift($links, $_link);
            // Add new links to the beginning
            esc_html('');
            return $links;
        }
        /**
         * logger
         *
         * @return Logger
         */
        public function logger()
        {
            return $this->_logger;
        }
        /**
         * run
         *
         * @return void
         */
        function run()
        {
            $order_systems = apply_filters('zjpp_order_system_list', []);
            $vendors = apply_filters('zjpp_vendor_list', []);
            $invoice_systems = apply_filters('zjpp_invoice_list', []);
            $prefs = get_option('ZJPP_prefs');
            foreach ($order_systems as $system_class) {
                $system_class = '\\ZJPP\\' . $system_class;
                if (!$system_class::meet_requirement()) continue;
                $system_id = $system_class::order_system_data()['id'];
                $enable = $prefs['order_systems'][$system_id]['enable'];
                if ('on' == $enable) {
                    foreach ($vendors as $vendor_class) {
                        $vendor_class = '\\ZJPP\\' . $vendor_class;
                        $methods = $vendor_class::gateway_data()['admin_fields']['available_methods']['items'];
                        $vendor_id = $vendor_class::gateway_data()['vendor']['id'];
                        if ('on' !== $prefs['payment_vendors'][$vendor_id]['enable']) continue;
                        foreach ($methods as $method_id => $method_item) {
                            if ('on' == $prefs['payment_vendors'][$vendor_id]['available_methods'][$method_id])
                                \ZJPP\PaymentSystemCreator::create_order_payment_system($system_class, $vendor_class, $method_id);
                        }
                    }
                }
            }
        }
        /**
         * __construct
         *
         * @return void
         */
        function __construct()
        {
            $this->_logger = \ZJPP\Logger::instance();
            $this->admin = ZJPP\Admin::instance();
            add_action(
                "plugin_action_links_{$this->get_plugin_basename()}",
                function ($links) {
                    return $this->add_plugin_links($links);
                }
            );
        }
    }
    /**
     * ZJPP
     *
     * @return ZJPP_Main
     */
    function ZJPP()
    {
        return ZJPP_Main::instance();
    };
    (function () {
        add_action('plugins_loaded', function () {
            $r = load_plugin_textdomain(
                'zj-payment-packs',
                false,
                'zj-payment-packs/languages/'
            );
            ZJPP()->run();
        }, 11);
    })();
}
