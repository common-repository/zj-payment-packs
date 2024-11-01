<?php
// SDK外殼，用來處理WooCommerce相容性問題
namespace ZJPP;

require_once ZJPP_PLUGIN_DIR . '/includes/lib/ecpay/includes/ECPay.Payment.Integration.php';

final class ECPay_Woo_AllInOne extends ECPay_AllInOne
{

    //訂單查詢作業
    function QueryTradeInfo()
    {
        return $arFeedback = ECPay_Woo_QueryTradeInfo::CheckOut(array_merge($this->Query, array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType)), $this->HashKey, $this->HashIV, $this->ServiceURL);
    }

    //信用卡定期定額訂單查詢的方法
    function QueryPeriodCreditCardTradeInfo()
    {
        return $arFeedback = ECPay_Woo_QueryPeriodCreditCardTradeInfo::CheckOut(array_merge($this->Query, array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType)), $this->HashKey, $this->HashIV, $this->ServiceURL);
    }

    //信用卡關帳/退刷/取消/放棄的方法
    function DoAction()
    {
        return $arFeedback = ECPay_Woo_DoAction::CheckOut(array_merge($this->Action, array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType)), $this->HashKey, $this->HashIV, $this->ServiceURL);
    }

    //合作特店申請撥款
    function AioCapture()
    {
        return $arFeedback = ECPay_Woo_AioCapture::Capture(array_merge($this->Capture, array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType)), $this->HashKey, $this->HashIV, $this->ServiceURL);
    }

    //查詢信用卡單筆明細紀錄
    function QueryTrade()
    {
        return $arFeedback = ECPay_Woo_QueryTrade::CheckOut(array_merge($this->Trade, array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType)), $this->HashKey, $this->HashIV, $this->ServiceURL);
    }

    // 產生訂單(站內付)
    function CreateTrade()
    {
        $arParameters = array_merge(array('MerchantID' => $this->MerchantID, 'EncryptType' => $this->EncryptType), $this->Send);
        return $arFeedback = ECPay_Woo_CreateTrade::CheckOut($arParameters, $this->SendExtend, $this->HashKey, $this->HashIV, $this->ServiceURL);
    }
}

/**
 * cURL 設定值
 */
abstract class ECPay_Woo_Payment_Curl
{

    /**
     * @var int 逾時時間
     */
    const TIMEOUT = 30;
}

/**
 * 抽象類
 */
abstract class ECPay_Woo_Aio extends ECPay_Aio
{

    protected static function ServerPost($Params, $ServiceURL)
    {

        $fields_string = http_build_query($Params);

        $rs = wp_remote_post($ServiceURL, array(
            'method'      => 'POST',
            'timeout'     => ECPay_Woo_Payment_Curl::TIMEOUT,
            'headers'     => array(),
            'httpversion' => '1.0',
            'sslverify'   => true,
            'body'        => $fields_string
        ));

        if (is_wp_error($rs)) {
            throw new \Exception($rs->get_error_message());
        }

        return $rs['body'];
    }
}

class ECPay_Woo_QueryTradeInfo extends ECPay_QueryTradeInfo
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}

class ECPay_Woo_QueryPeriodCreditCardTradeInfo extends ECPay_QueryPeriodCreditCardTradeInfo
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}

class ECPay_Woo_DoAction extends ECPay_DoAction
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}

class ECPay_Woo_AioCapture extends ECPay_AioCapture
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}

class ECPay_Woo_QueryTrade extends ECPay_QueryTrade
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}

class ECPay_Woo_CreateTrade extends ECPay_CreateTrade
{
    protected static function ServerPost($Params, $ServiceURL)
    {
        return ECPay_Woo_Aio::ServerPost($Params, $ServiceURL);
    }
}
