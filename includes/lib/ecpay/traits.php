<?php

namespace zjpp;

if (!trait_exists('ZJPP\\ecpay_check_mac_value')) {
    /**
     *  check mac value for ecpay
     */
    trait ecpay_check_mac_value
    {
        /**
         * generate_check_mac_value
         *
         * @param  array  $data
         * @param  string $HashKey
         * @param  string $HashIV
         * @param  bool   $use_md5
         * @return string
         */
        function generate_check_mac_value($data = array(), $HashKey = '', $HashIV = '', $use_md5 = false)
        {
            function replace_symbol($input_string)
            {
                if (!empty($input_string)) {

                    $input_string = str_replace('%2D', '-', $input_string);
                    $input_string = str_replace('%2d', '-', $input_string);
                    $input_string = str_replace('%5F', '_', $input_string);
                    $input_string = str_replace('%5f', '_', $input_string);
                    $input_string = str_replace('%2E', '.', $input_string);
                    $input_string = str_replace('%2e', '.', $input_string);
                    $input_string = str_replace('%21', '!', $input_string);
                    $input_string = str_replace('%2A', '*', $input_string);
                    $input_string = str_replace('%2a', '*', $input_string);
                    $input_string = str_replace('%28', '(', $input_string);
                    $input_string = str_replace('%29', ')', $input_string);
                }
                return $input_string;
            }
            $mac_value = '';
            if (isset($data)) {
                unset($data['CheckMacValue']);
                uksort($data, function ($a, $b) {
                    return strcasecmp($a, $b);
                });
                $mac_value = 'HashKey=' . $HashKey;
                foreach ($data as $key => $value) {
                    $mac_value .= '&' . $key . '=' . $value;
                }
                $mac_value .= '&HashIV=' . $HashIV;
                $mac_value = urlencode($mac_value);
                $mac_value = strtolower($mac_value);
                $mac_value = replace_symbol($mac_value);
                if (!$use_md5)
                    $mac_value = hash('sha256', $mac_value);
                else
                    $mac_value = md5($mac_value);

                $mac_value = strtoupper($mac_value);
            }
            return $mac_value;
        }
    }
}

if (!trait_exists('ZJPP\\ecpay_aes')) {
    trait ecpay_aes
    {
        /**
         * encrypt_data
         *
         * @param  array $data
         * @return string
         */
        protected function encrypt_data($data)
        {
            if (openssl_cipher_iv_length('AES-128-CBC') !== strlen($this->hash_key)) {
                throw new \LogicException('invalid hash key!');
            }
            if (openssl_cipher_iv_length('AES-128-CBC') !== strlen($this->hash_iv)) {
                throw new \LogicException('invalid hash iv!');
            }
            $_data = json_encode($data);
            $_data = urlencode($_data);
            $_data = openssl_encrypt($_data, 'AES-128-CBC', $this->hash_key, 0, $this->hash_iv);
            return $_data;
        }
        /**
         * decrypt_data
         *
         * @param  string $data
         * @return array
         */
        protected function decrypt_data($data)
        {
            if (openssl_cipher_iv_length('AES-128-CBC') !== strlen($this->hash_key)) {
                throw new \LogicException('invalid hash key!');
            }
            if (openssl_cipher_iv_length('AES-128-CBC') !== strlen($this->hash_iv)) {
                throw new \LogicException('invalid hash iv!');
            }
            $_data = openssl_decrypt($data, 'AES-128-CBC', $this->hash_key, 0, $this->hash_iv);
            $_data = urldecode($_data);
            $_data = (array) json_decode($_data);
            return $_data;
        }
    }
}
