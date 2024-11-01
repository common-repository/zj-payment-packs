<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

/**
 * Admin
 */
final class Admin
{
    use singleton;
    const max_field_length = 50;
    /**
     * parse_field_name
     *
     * @param  string $name
     * @return array
     */
    private function parse_field_name($name)
    {
        preg_match_all('/\[(.+?)\]/', $name, $matches);
        $keys = $matches[1];
        return $keys;
    }
    /**
     * value
     *
     * @param  array $prefs
     * @param  array $name
     * @return mixed
     */
    private function get_value($prefs, $name)
    {
        $keys = $this->parse_field_name($name);
        $v = $prefs;
        foreach ($keys as $key) {
            $v = $v[$key];
        }
        return isset($v) ? $v : '';
    }
    /**
     * set_value
     *
     * @param  array $prefs
     * @param  array $name
     * @return mixed
     */
    private function set_value($prefs, $name, $val)
    {
        $keys = $this->parse_field_name($name);
        $v = &$prefs;
        foreach ($keys as $index => $key) {
            if ($index < count($keys) - 1) {
                if (!isset($v[$key])) {
                    $v[$key] = [];
                }
                $v = &$v[$key];
            } else {
                $v[$key] = $val;
            }
        }
        return $prefs;
    }
    /**
     * rebuild_prefs
     *
     * @param  array $prefs
     * @return array
     */
    private function rebuild_prefs($prefs)
    {
        ob_start();
        $this->setup_page();
        $content = ob_get_clean();
        $xmlDoc = new \DOMDocument();
        $r = $xmlDoc->loadHTML($content);
        $inputs = $xmlDoc->getElementsByTagName("input");
        $defs = [];
        foreach ($inputs as $input) {
            $def = [
                'name' => sanitize_text_field($input->getAttribute('name')),
                'type' => sanitize_text_field($input->getAttribute('type')),
                'length' => self::max_field_length,
            ];
            $length = sanitize_text_field($input->getAttribute('length'));
            if (filter_var($length, FILTER_VALIDATE_INT) && intval($length) < self::max_field_length) {
                $def['length'] = intval($length);
            }
            $defs[] = $def;
        }
        $inputs = $xmlDoc->getElementsByTagName("select");
        foreach ($inputs as $input) {
            $def = [
                'name' => sanitize_text_field($input->getAttribute('name')),
                'type' => 'select',
            ];
            $defs[] = $def;
        }
        // rebuild the input array to filter garbages
        $res = [];
        foreach ($defs as $def) {
            $name = $def['name'];
            $val = $this->get_value($prefs, $name);
            $val = sanitize_text_field($val);
            $type = $def['type'];
            switch ($type) {
                case 'checkbox':
                    if ('on' !== $val) {
                        $val = 'off';
                    }
                    break;
                case 'select':
                    break;
                case 'text':
                default:
                    $val = substr($val, 0, $def['length']);
                    break;
            }
            $res = $this->set_value($res, $name, $val);
        }
        // validate the input fields
        foreach ($defs as $def) {
            $name = $def['name'];
            $type = $def['type'];
            $val = $this->get_value($res, $name);
            $val = apply_filters('zjpp_validate_fields', $val, $this->parse_field_name($name), $res);
            $res = $this->set_value($res, $name, $val);
        }
        return $res;
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
        $vendors = apply_filters('zjpp_vendor_list', []);
        foreach ($vendors as $id => $vendor) {
            $vendor = '\\ZJPP\\' . $vendor;
            if (is_callable(array($vendor, 'validate_fields')))
                $val = $vendor::validate_fields($val, $name, $res);
        }
        return $val;
    }

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $systems = ['OrderSystem_buycred', 'OrderSystem_EDD', 'OrderSystem_woocommerce'];
        $vendors = ['PaymentVendor_ecpay', 'PaymentVendor_neweb', 'PaymentVendor_linepay'];
        $invoices = ['InvoiceSystem_ecpay', 'InvoiceSystem_ezpay'];
        foreach ($systems as $system) {
            require_once ZJPP_PLUGIN_DIR . '/includes/order_systems/' . $system . '.php';
        }
        foreach ($vendors as $vendor) {
            require_once ZJPP_PLUGIN_DIR . '/includes/payment_vendors/' . $vendor . '.php';
        }
        foreach ($invoices as $invoice) {
            require_once ZJPP_PLUGIN_DIR . '/includes/invoice_systems/' . $invoice . '.php';
        }
    }
    public function setup_page()
    {
        $order_systems = apply_filters('zjpp_order_system_list', []);
        $vendors = apply_filters('zjpp_vendor_list', []);
        $invoice_systems = apply_filters('zjpp_invoice_system_list', []);
        $prefs = get_option('ZJPP_prefs', []);
?>
        <h1><?php esc_html_e('ZJ Payment Packs', 'zj-payment-packs'); ?></h1>
        <form action="<?php echo esc_attr(admin_url('options.php')); ?>" method="post">
            <?php settings_fields('ZJPP_setting_fields'); ?>
            <div>
                <h4><?php esc_html_e('Please choose order systems to use:', 'zj-payment-packs'); ?></h4>
            </div>
            <div class="order_system_container">
                <?php
                foreach ($order_systems as $order_system) { ?>
                    <div class="order_system">
                        <?php ('\\ZJPP\\' . $order_system)::show_admin_settings(); ?>
                    </div>
                <?php }
                ?>
            </div>
            <div>
                <h4><?php esc_html_e('Please choose payment vendors to use:', 'zj-payment-packs'); ?></h4>
            </div>
            <div class="payment_container">
                <?php
                foreach ($vendors as $id => $vendor) { ?>
                    <div class="payment_method">
                        <?php ('\\ZJPP\\' . $vendor)::show_admin_settings(); ?>
                    </div>
                <?php } ?>
            </div>
            <div class="invoice_container" >
                <h4><?php esc_html_e('發票功能設定:', 'zj-payment-packs'); ?></h4>
                <div class="item">
                    <label for="invoice_enable">
                        <input type="checkbox" id="invoice_enable" name="ZJPP_prefs[invoice_enable]" <?php echo $prefs['invoice_enable'] == 'on' ? 'checked' : ''; ?>>
                        <?php esc_html_e('啟用發票功能', 'zj-payment-packs'); ?>
                    </label>
                </div>
                <div class="item">
                    <label for="invoice_auto">
                        <input type="checkbox" id="invoice_auto" name="ZJPP_prefs[invoice_auto]" <?php echo $prefs['invoice_auto'] == 'on' ? 'checked' : ''; ?>>
                        <?php esc_html_e('完成交易後自動產生發票', 'zj-payment-packs'); ?>
                    </label>
                </div>
                <div class="item">
                    <label for="invoice_disable_print">
                        <input type="checkbox" id="invoice_disable_print" name="ZJPP_prefs[invoice_disable_print]" <?php echo $prefs['invoice_disable_print'] == 'on' ? 'checked' : ''; ?>>
                        <?php esc_html_e('不使用紙本發票(適用於虛擬商品或服務)', 'zj-payment-packs'); ?>
                    </label>
                </div>
                <div class="item">
                    <label for="invoice_method">
                        <span>
                            <?php esc_html_e('選擇開立發票服務廠家:', 'zj-payment-packs'); ?>
                        </span>
                        <span style="margin:50px;">
                            <select id="invoice_method" name="ZJPP_prefs[invoice_method]">
                                <?php
                                foreach ($invoice_systems as $invoice_system) {
                                    $invoice_system_id = ('\\ZJPP\\' . $invoice_system)::invoice_system_id();
                                    $invoice_system_title = ('\\ZJPP\\' . $invoice_system)::invoice_system_title();
                                ?>
                                    <option value="<?php echo $invoice_system_id; ?>" <?php echo $prefs['invoice_method'] == $invoice_system_id ? 'selected' : ''; ?>><?php echo esc_html($invoice_system_title); ?></option>
                                <?php
                                } ?>
                            </select>
                        </span>
                    </label>
                </div>
                <div class="invoice_systems">
                    <?php
                    foreach ($invoice_systems as $id => $invoice_system) { ?>
                        <div class="invoice_system">
                            <?php ('\\ZJPP\\' . $invoice_system)::show_admin_settings(); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php submit_button(); ?>
        </form>
<?php
    }

    /**
     * option_page
     *
     * @return void
     */
    public function option_page()
    {
        wp_enqueue_style('ZJPP_admin', ZJPP_PLUGIN_URL . '/admin/assets/admin.css');
        wp_enqueue_script('ZJPP_admin', ZJPP_PLUGIN_URL . '/admin/assets/admin.js');
        add_options_page(
            __('ZJ Payment Packs', 'zj-payment-packs'),
            __('ZJ Payment Packs', 'zj-payment-packs'),
            'manage_options',
            'ZJPP_setting_fields',
            array($this, 'setup_page')
        );
    }

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        add_action('admin_init', function () {
            register_setting('ZJPP_setting_fields', 'ZJPP_prefs');
            add_filter('pre_update_option_ZJPP_prefs', function ($value, $old_value, $option) {
                $new_value = $this->rebuild_prefs($value);
                return $new_value;
            }, 10, 3);
            add_filter('zjpp_validate_fields', array('\\ZJPP\\OrderSystem', 'validate_fields'), 10, 3);
            add_filter('zjpp_validate_fields', array($this, 'validate_fields'), 10, 3);
        });
        add_action('admin_menu', array($this, 'option_page'));

        $this->init();
    }
}
