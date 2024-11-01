<?php

namespace ZJPP;

if (!trait_exists('ZJPP\\singleton')) {
    trait singleton
    {
        private static $_instance;
        /**
         * instance
         *
         * @return self
         */
        public static function instance()
        {
            if (!static::$_instance) {
                static::$_instance = new static();
            }
            return static::$_instance;
        }
    }
}

if (!trait_exists('ZJPP\\embedded_payment_system')) {
    trait embedded_payment_system
    {
        static $_system = null;
        /**
         * system
         *
         * @return mixed
         */
        public function system()
        {
            return self::$_system;
        }
        /**
         * set_system
         *
         * @param  mixed $_sys
         * @return void
         */
        public function set_system($_sys)
        {
            self::$_system = $_sys;
        }
    }
}

if (!trait_exists('ZJPP\\remote_post')) {
    trait remote_post
    {
        /**
         * Server Post
         *
         * @param     array    $parameters    Post 參數
         * @param     string   $ServiceURL    Post URL
         * @return    array|string
         */
        protected function send_post_request($parameters, $ServiceURL, $use_json = true)
        {
            $output_data = $use_json ? json_encode($parameters) : http_build_query($parameters);
            $rs = wp_remote_post($ServiceURL, array(
                'method' => 'POST',
                'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
                'body'        => $output_data,
            ));

            if (is_wp_error($rs)) {
                throw new \Exception($rs->get_error_message());
            }
            $res = (array) json_decode($rs['body']);
            return $res ? $res : $rs['body'];
        }
    }
}
