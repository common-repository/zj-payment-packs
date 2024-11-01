<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/neweb/traits.php';

/**
 * NewebHelper
 */
class NewebHelper
{
    use neweb_check_code;
    use neweb_aes;
    /**
     * send_data
     *
     * @param  mixed $data
     * @param  mixed $url
     * @return void
     */
    function send_data($data, $url)
    {
?>
        <form id="sending_form" action="<?php echo esc_attr($url); ?>" method="post">
            <?php
            foreach ($data as $key => $value) {
            ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
            <?php
            } ?>
            <script>
                document.getElementById('sending_form').submit();
            </script>
        </form>
<?php
    }
}
