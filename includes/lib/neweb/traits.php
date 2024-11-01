<?php

namespace ZJPP;

if (!trait_exists('ZJPP\\neweb_check_code')) {
    trait neweb_check_code
    {
        /**
         * check_sha_is_vaild_by_return_data
         *
         * @param  mixed $key
         * @param  mixed $iv
         * @return bool
         */
        function check_sha_is_vaild_by_return_data($key, $iv)
        {
            if (empty($_POST['TradeSha'])) return false;
            if (empty($_POST['TradeInfo'])) return false;
            $local_sha = $this->aes_sha256_str($_POST['TradeInfo'], trim($key), trim($iv));
            if ($_POST['TradeSha'] != $local_sha) return false;
            return true;
        }
        /**
         * getCheckCode
         *
         * @param  array $data
         * @param  string $hash_key
         * @param  string $hash_iv
         * @return string
         */
        function get_check_code($data, $hash_key, $hash_iv)
        {
            $sMacValue = '';
            if (isset($data)) {
                uksort($data, function ($a, $b) {
                    return strcasecmp($a, $b);
                });

                $sMacValue .= 'HashIV=' . $hash_iv;
                foreach ($data as $key => $value) {
                    $sMacValue .= '&' . $key . '=' . $value;
                }
                $sMacValue .= '&HashKey=' . $hash_key;
                $sMacValue = hash('sha256', $sMacValue);
                $sMacValue = strtoupper($sMacValue);
            }
            return $sMacValue;
        }
    }
}

if (!trait_exists('ZJPP\\neweb_aes')) {
    trait neweb_aes
    {
        /**
         *MPG aes解密
         *
         * @access private
         * @param array $parameter ,string $key, string $iv
         * @version 1.4
         * @return array|boolean
         */
        function create_aes_decrypt($parameter = "", $key = "", $iv = "")
        {
            // $decrypt_data = $this->strippadding(@mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key,
            //     hex2bin($parameter), MCRYPT_MODE_CBC, $iv));
            $decrypt_data = $this->strippadding(openssl_decrypt(hex2bin($parameter), 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv));
            if (!$decrypt_data) return false;
            if (json_decode($decrypt_data)) {
                $return_data = $this->decrypt_json_data($decrypt_data);
            } else {
                $return_data = $this->decrypt_str_data($decrypt_data);
            }

            return $return_data;
        }
        /**
         * strippadding
         *
         * @param  mixed $string
         * @return string|bool
         */
        private function strippadding($string)
        {
            $slast = ord(substr($string, -1));
            $slastc = chr($slast);
            if (preg_match("/$slastc{" . $slast . "}/", $string)) {
                $string = substr($string, 0, strlen($string) - $slast);
                return $string;
            } else {
                return false;
            }
        }
        /**
         * decrypt_json_data
         *
         * @param  mixed $dec_str
         * @return array
         */
        private function decrypt_json_data($dec_str)
        {
            $dec_data = json_decode($dec_str, true);
            $dec_data['Result']['Status'] = $dec_data['Status'];
            $dec_data['Result']['Message'] = $dec_data['Message'];
            return $dec_data['Result']; //整理成跟String回傳相同格式
        }
        /**
         * decrypt_str_data
         *
         * @param  mixed $dec_str
         * @return array
         */
        private function decrypt_str_data($dec_str)
        {
            $dec_data = explode('&', $dec_str);
            foreach ($dec_data as $_ind => $value) {
                $trans_data = explode('=', $value);
                $return_data[$trans_data[0]] = $trans_data[1];
            }
            return $return_data;
        }
        /**
         * addpadding
         *
         * @param  mixed $string
         * @param  mixed $blocksize
         * @return string
         */
        private function addpadding($string, $blocksize = 32)
        {
            $len = strlen($string);
            $pad = $blocksize - ($len % $blocksize);
            $string .= str_repeat(chr($pad), $pad);
            return $string;
        }
        /**
         *MPG sha256加密
         *
         * @access private
         * @param string $str ,string $key, string $iv
         * @version 1.4
         * @return string
         */
        function aes_sha256_str($str, $key = "", $iv = "")
        {
            return strtoupper(hash("sha256", 'HashKey=' . $key . '&' . $str . '&HashIV=' . $iv));
        }
        /**
         *MPG aes加密
         *
         * @access private
         * @param array $parameter ,string $key, string $iv
         * @version 1.4
         * @return string
         */
        function create_mpg_aes_encrypt($parameter, $key = "", $iv = "")
        {
            $return_str = '';
            if (!empty($parameter)) {
                ksort($parameter);
                $return_str = http_build_query($parameter);
            }
            return trim(bin2hex(openssl_encrypt($this->addpadding($return_str), 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv)));
        }
    }
}
