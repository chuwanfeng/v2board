<?php

namespace App\Payments;

use App\Models\Order;

class CommercePay
{
    //接口地址
    private $apiurl = 'https://payments.commerce.asia';

    public function __construct($config) {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'commerce_currencycode' => [
                'label' => '货币',
                'description' => 'CNY（人民币），MYR（马币）',
                'type' => 'input',
            ],
            'commerce_secret_key' => [
                'label' => 'Secret Key',
                'description' => '',
                'type' => 'input',
            ],
            'commerce_tenant_id' => [
                'label' => 'Tenant ID',
                'description' => '',
                'type' => 'input',
            ],
            'commerce_username' => [
                'label' => '用户名',
                'description' => '',
                'type' => 'input',
            ],
            'commerce_password' => [
                'label' => '密码',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order) {
        //获得authenticate
        //$this->authenticate = $this->getau();
        //abort(500, $this->authenticate);

        //签名
        $postdata = $this->getpostdata($order);
        $signString = strtolower($this->apiurl.'/api/services/app/PaymentGateway/RequestPayment'.''.json_encode($postdata));
        $signature = hash_hmac('sha256', stripslashes($signString), $this->config['commerce_secret_key']);

        $request_headers = array(
            //'Authorization'.$this->authenticate.'',
            'Content-type: application/json-patch+json',
            'Accept: text/plain',
            'Abp.TenantId:'.$this->config['commerce_tenant_id'],
            'cap-signature: '.$signature.'',
        );

        //abort(500,stripslashes(json_encode($postdata)));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiurl.'/api/services/app/PaymentGateway/RequestPayment');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, stripslashes(json_encode($postdata)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($ch);
        curl_close($ch);
        $resdata = json_decode($response, true);

        if(empty($resdata['result']['redirectUrl'])){
            //如果获得的付款页为空
            abort(500, $response);
        }



        return [
            'type' => 1,
            'data' => $resdata['result']['redirectUrl'],
        ];
    }

    public function notify($params) {
        $payload = trim(request()->getContent() ?: json_encode($_POST));
        $json_param = json_decode($payload, true);

        $transactionNumber = $json_param['transactionNumber'];
        $timestamp = time();
        $postdata = [
            'Timestamp' => $timestamp,
            'TransactionNumber' => $json_param['transactionNumber']
        ];

        $signString = strtolower($this->apiurl.'/api/services/app/PaymentGateway/Query'.''.json_encode($postdata));
        $signature = hash_hmac('sha256', stripslashes($signString), $this->config['commerce_secret_key']);


        $url = $this->apiurl.'/api/services/app/PaymentGateway/Query?TransactionNumber=' . $transactionNumber . '&Timestamp=' . $timestamp;
        $headers = array(
            'Accept: application/json, text/json',
            'Abp.TenantId:'.$this->config['commerce_tenant_id'],
            'cap-signature: '.$signature.'',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);
        $resdata = json_decode($response, true);

        // 处理响应数据
        if ($resdata['result']['status'] != 1) {
            return false;
        } else {
            return [
                'trade_no' => $resdata['result']['referenceCode'],
                'callback_no' => $resdata['result']['transactionNumber']
            ];
        }
    }

    private function getpostdata($order)
    {
        $postdata = array(
            'amount' => intval($order['total_amount']),
            'callbackUrl' => $order['notify_url'],
            "channelId" => 22,
            'currencyCode' => $this->config['commerce_currencycode'],
            'customer' => [
                'email' => "123456@qq.com",
                'mobileNo' => "123456",
                'name' => "黑马",
                'username' => "黑马",
            ],
            'description' => "黑马虚拟商品",
            'ipAddress' => "127.0.0.1",
            'localCitizen' => FALSE,
            'referenceCode' => strval($order['trade_no'],),
            //'returnUrl' => $order['return_url'],
            'returnUrl' => "https://api.heima2u.com/return_url.php",
            //'tenantId' => intval($this->config['commerce_tenant_id']),
            'timestamp' => $order['created_at'],
            'userAgent' => "Mozilla/5.0",
        );
        return $postdata;
    }

    private function getau()
    {
        $url = $this->apiurl.'/api/TokenAuth/Authenticate';
        $headers = array(
            'Abp.TenantId: ' . $this->config['commerce_tenant_id'],
            'Accept: application/json',
            'Content-Type: application/json'
        );
        $data = array(
            'userNameOrEmailAddress' => $this->config['commerce_username'],
            'password' => $this->config['commerce_password']
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $ret = json_decode($response, true);
        if(!$ret['success']){
            //如果获得的Token为空
            return false;
        }else{

            return $ret['result']['accessToken'];
        }
    }
}
