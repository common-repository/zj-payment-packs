<?php

namespace ZJPP;

trait HtmlFields
{    
    /**
     * html_fields_section
     *
     * @return void
     */
    protected static function html_fields_section()
    {
        return '';
    }
    /**
     * load_prefs
     *
     * @return array
     */
    protected static function load_prefs_all()
    {
        $prefs = get_option('ZJPP_prefs', []);
        return $prefs;
    }
    /**
     * value
     *
     * @param  mixed $prefs
     * @param  mixed $name
     * @return mixed
     */
    public static function value($prefs, $name)
    {
        preg_match_all('/\[(.+?)\]/', $name, $matches);
        $keys = $matches[1];
        $v = $prefs;
        foreach ($keys as $key) {
            $v = $v[$key];
        }
        return isset($v) ? $v : '';
    }

    /**
     * show_field
     *
     * @param  mixed $fields
     * @param  mixed $name
     * @param  mixed $id
     * @param  mixed $payment_method_name
     * @return void
     */
    protected static function show_field($attrs, $prefs, $name, $id)
    {
        $id = $id;
        $name = $name;
        $val = '';
        if ('text' == $attrs['type']) {
            $val = static::value($prefs, $name); ?>
            <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="text" value="<?php echo esc_attr($val); ?>">
        <?php
        } else if ('checkbox' == $attrs['type']) {
            $val = static::value($prefs, $name); ?>
            <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="checkbox" <?php echo (('on' == esc_attr($val)) ? esc_attr('checked') : esc_attr('')); ?>>
            <?php
        } else if ('checkbox_list' == $attrs['type']) {
            $items = $attrs['items'];
            foreach ($items as $item_id => $item) {
                $item_id = $item_id;
                $item_name = $name . "[${item_id}]";
                $item_id = $id . '_' . $item_id;
                $val = static::value($prefs, $item_name); ?>
                <div>
                    <label for="<?php echo esc_attr($item_id); ?>">
                        <input id="<?php echo esc_attr($item_id); ?>" name="<?php echo esc_attr($item_name); ?>" type="checkbox" <?php echo (('on' == $val) ? esc_attr('checked') : esc_attr('')); ?>>
                        <?php echo esc_html($item['label']); ?>
                    </label>
                </div>
            <?php
            }
        }
    }
    /**
     * show_admin_settings
     *
     * @return void
     */
    public static function show_admin_settings()
    {
        $prefs = static::load_prefs_all();
        $data = static::defs();
        $vendor_id = $data['id'];
        $vendor_title = $data['title'];
        $rows = $data['admin_fields'];
        $append_rows = [];
        if ($rows) {
            if ('off' !== $data['enable_option']) {
                $append_rows['enable'] = [
                    'label' => __('Enable', 'zj-payment-packs'),
                    'type' => 'checkbox',
                    'value' => $prefs['enable'],
                ];
            }
            if ('on' === $data['test_mode']) {
                $append_rows['test_mode'] = [
                    'label' => __('Test Mode', 'zj-payment-packs'),
                    'type' => 'checkbox',
                    'value' => $prefs['test_mode'],
                ];
            }
            if ($rows['test_mode']['description']) {
                $append_rows['test_mode']['description'] = $rows['test_mode']['description'];
                unset($rows['test_mode']);
            }
            if ($rows['enable']['description']) {
                $append_rows['enable']['description'] = $rows['enable']['description'];
                unset($rows['enable']);
            }
            $rows = array_merge($append_rows, $rows);
            ?>
            <table class="table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th class="item" colspan="2"><?php echo esc_html($vendor_title); ?></th>
                    </tr>
                </thead>
                <tbody><?php
                        foreach ($rows as $id => $attrs) {
                            $section = static::html_fields_section();
                            $tag_name = "ZJPP_prefs[$section][${vendor_id}][${id}]";
                            $tag_id = $vendor_id . '_' . $id; ?>
                        <tr>
                            <td class="label"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($attrs['label']); ?></label></td>
                            <td class="item"><?php static::show_field($attrs, $prefs, $tag_name, $tag_id); ?></td>
                        </tr>
                        <tr>
                            <td class="label"></td>
                            <td><?php echo esc_html($attrs['description']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
<?php
        }
    }
}
