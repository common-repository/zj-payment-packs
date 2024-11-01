<?php

namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/traits.php';

class Logger
{
    use singleton;
    /**
     * log
     *
     * @param  mixed $content
     * @return void
     */
    protected function log($content, $level = 0)
    {
        $prefix = '';
        switch ($level) {
            case 1:
                $prefix = ' [Warning] ';
                break;
            case 2:
                $prefix = ' [Error] ';
                break;
            default:
            case 0:
                $prefix = ' [Info] ';
                break;
        }

        file_put_contents(ZJPP_PLUGIN_LOG_PATH, date("Y-m-d H:i:s") . $prefix . $content . PHP_EOL, FILE_APPEND);
    }
    /**
     * log_info
     *
     * @param  mixed $content
     * @return void
     */
    public function info($content)
    {
        $this->log($content, 0);
    }
    /**
     * log_warning
     *
     * @param  mixed $content
     * @return void
     */
    public function warning($content)
    {
        $this->log($content, 1);
    }
    /**
     * log_error
     *
     * @param  mixed $content
     * @return void
     */
    public function error($content)
    {
        $this->log($content, 2);
    }
}
