<?php

namespace ZJPP;

if (!trait_exists('ezpay_check_code')) {
    /**
     *  check mac value for ecpay
     */
    trait ezpay_check_code
    {
        /**
         * generate_check_code
         *
         * @param  array $data
         * @param  string $HashKey
         * @param  string $HashIV
         * @return string
         */
        function generate_check_code($data = array(), $HashKey = '', $HashIV = '')
        {
            $mac_value = '';
            if (isset($data)) {
                uksort($data, function ($a, $b) {
                    return strcasecmp($a, $b);
                });
                $mac_value = 'HashIV=' . $HashIV . http_build_query($data) . '&HashKey=' . $HashKey;
                $mac_value = hash('sha256', $mac_value);
                $mac_value = strtoupper($mac_value);
            }
            return $mac_value;
        }
    }
}
