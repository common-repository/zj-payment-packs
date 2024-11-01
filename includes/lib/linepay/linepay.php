<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

/**
 * LibLinepay
 */
class LibLinepay
{
    use singleton;
    /**
     * LINE Pay API connect endpoint for Production.
     *
     * @var string
     */
    public static $api_endpoint_production;

    /**
     * LINE Pay API connect channel id for Production.
     *
     * @var string
     */
    public static $api_channel_id;

    /**
     * LINE Pay API connect channel secret key for Production.
     *
     * @var string
     */
    public static $api_channel_secret_key;

    /**
     * Order Prefix text
     *
     * @var string
     */
    public static $order_prefix;

    /**
     * Debug mode
     *
     * @var string
     */
    public $debug;

    private static function json_custom_decode($json)
    {
        return json_decode($json, false, 512, JSON_BIGINT_AS_STRING);
    }
    /**
     * Make debug message with order id.
     *
     * @param string $order_id
     * @param string $message
     * @throws
     * @return string
     */
    public function make_debug_message($message, $order_id = null)
    {
        $return_message = $message;
        if (isset($order_id)) {
            $return_message = 'Order ID :' . $order_id . ';' . "\n" . $message;
        }
        return $return_message;
    }
    /**
     * Make message for LinePay Confirm API response
     *
     * @param object $response
     * @return string $response_message
     */
    public function response_confirm_message($response)
    {
        $response_array['returnCode'] = $response->returnCode;
        $response_array['returnMessage'] = $response->returnMessage;
        if (isset($response->info)) {
            $response_array['info.transactionId'] = $response->info->transactionId;
            $response_array['info.orderId'] = $response->info->orderId;
        }
        $response_message = print_r($response_array, true);
        return $response_message;
    }
    /**
     * set send header for LINE Pay API
     *
     * @param string $requestUri
     * @param string $content
     * @throws
     * @return array
     */
    public function get_linepay_headers($requestUri, $content)
    {
        $none = uniqid();
        $body = static::$api_channel_secret_key . $requestUri . $content . $none;
        $signature = base64_encode(hash_hmac('sha256', $body, static::$api_channel_secret_key, true));
        return array(
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-LINE-ChannelId' => static::$api_channel_id,
            'X-LINE-Authorization-Nonce' => $none,
            'X-LINE-Authorization' => $signature
        );
    }

    /**
     * Send data by Post data for LINE Pay API
     *
     * @param string $uri
     * @param string $body
     * @param string $debug
     * @param string $send_method
     * @param string $order_id
     * @throws
     * @return array | mixed
     */
    public function send_api_linepay($uri, $parameters, $debug, $send_method, $order_id = null)
    {
        $url = static::$api_endpoint_production;
        $body = '';
        if ($send_method == 'POST') {
            $body = json_encode($parameters);
        } else if ($send_method == 'GET') {
            $body = http_build_query($parameters);
        }
        $headers = $this->get_linepay_headers($uri, $body);
        $args = array(
            'method' => $send_method,
            'httpversion'    => '1.1',
            'timeout'        => 20,
            'headers' => $headers,
        );
        $args['body'] = '';
        if ($send_method == 'POST') {
            $args['body'] = $body;
        } elseif ($send_method == 'GET') {
            $uri .= '?' . $body;
        }
        $response = wp_remote_post($url . $uri, $args);
        if (is_wp_error($response)) {
            $error_message = $this->make_debug_message($response->get_error_message(), $order_id);
            ZJPP()->logger()->error($error_message);
            return false;
        } elseif ($response['response']['code'] != 200) {
            $res_error_message = $this->make_debug_message($response['response']['code'] . ' - ' . $response['response']['message'], $order_id);
            ZJPP()->logger()->error($res_error_message);
            return false;
        } else {
            $response_body = static::json_custom_decode($response['body']);
            $response_message = $this->make_debug_message(var_export($response_body, true), $order_id);
            ZJPP()->logger()->info($response_message);
            return $response_body;
        }
    }
    /**
     * Make array for LinePay Request API order data
     *
     * @param object $order
     * @return array $post_data
     */
    public function set_api_order($order_id, $total, $subscribe)
    {
        $post_data['amount'] = round($total);
        $post_data['currency'] = 'TWD';
        $post_data['orderId'] = $order_id;
        //Set package data
        $packages['id'] = 1;
        $packages['name'] = $this->shop_name;
        $packages['amount'] = round($total);
        $packages['products'] = [
            ['name' => esc_attr(get_bloginfo('name') . '-' . $order_id), 'quantity' => 1, 'price' => round($total)]
        ];
        $post_data['packages'] = array($packages);

        //Set Payment capture
        $post_data['options']['payment']['capture'] = true;
        if ($subscribe)
            $post_data['options']['payment']['payType'] = 'PREAPPROVED';

        //Set Display language
        $allowed_langs = array('ja', 'th', 'zh_TW', 'zh_CN');
        $wp_current_lang = get_locale();
        if (strpos($wp_current_lang, 'en') !== false) {
            $current_lang = 'en';
        } elseif (strpos($wp_current_lang, 'ko') !== false) {
            $current_lang = 'ko';
        } elseif (in_array($wp_current_lang, $allowed_langs)) {
            $current_lang = $wp_current_lang;
        } else {
            $current_lang = 'en';
        }
        $post_data['options']['display']['locale'] = $current_lang;
        return $post_data;
    }
    /**
     * Make message for LinePay Request API response
     *
     * @param object $response
     * @return string $response_message
     */
    public function response_request_message($response)
    {
        $response_array['returnCode'] = $response->returnCode;
        $response_array['returnMessage'] = $response->returnMessage;
        if (isset($response->info)) {
            $response_array['info.transactionId'] = $response->info->transactionId;
            $response_array['info.paymentAccessToken'] = $response->info->paymentAccessToken;
            $response_array['info.paymentUrl.app'] = $response->info->paymentUrl->app;
            $response_array['info.paymentUrl.web'] = $response->info->paymentUrl->web;
        }
        $response_message = print_r($response_array, true);
        return $response_message;
    }
    /**
     * Function to determine whether it is a smartphone.
     *
     * @return boolean
     */
    public function isSmartPhone()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        if (
            stripos($ua, 'iphone') !== false || // iphone
            stripos($ua, 'ipod') !== false || // ipod
            (stripos($ua, 'android') !== false && stripos($ua, 'mobile') !== false) || // android
            (stripos($ua, 'windows') !== false && stripos($ua, 'mobile') !== false) || // windows phone
            (stripos($ua, 'firefox') !== false && stripos($ua, 'mobile') !== false) || // firefox phone
            (stripos($ua, 'bb10') !== false && stripos($ua, 'mobile') !== false) || // blackberry 10
            (stripos($ua, 'blackberry') !== false) // blackberry
        ) {
            $isSmartPhone = true;
        } else {
            $isSmartPhone = false;
        }

        return $isSmartPhone;
    }

    function __construct()
    {
        $this->debug = false;
        $this->shop_name = get_bloginfo('name');
    }
}
