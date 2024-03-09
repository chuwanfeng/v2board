<?php

namespace App\Payments;

use GuzzleHttp\Exception\GuzzleException;
use UniPayment\Client\ApiException;

class MyUniPayment
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'app_id' => [
                'label' => 'UniPayment APPID',
                'description' => '',
                'type' => 'input',
            ],
            'client_id' => [
                'label' => 'UniPayment 客户端ID',
                'description' => '',
                'type' => 'input',
            ],
            'client_secret' => [
                'label' => 'UniPayment 客户端密匙',
                'description' => '',
                'type' => 'input',
            ],
            'currency' => [
                'label' => '货币单位',
                'description' => 'CNY（人民币），USD（美元）',
                'type' => 'input',
            ],
            'product_name' => [
                'label' => '自定义商品名称',
                'description' => '将会体现在UniPayment账单中',
                'type' => 'input'
            ],
            'lang' => [
                'label' => '语言',
                'description' => 'en（英语），zh-Hans（简体中文）',
                'type' => 'input'
            ]
        ];
    }

    public function pay($order)
    {
        try {
            $createInvoiceRequest = new \UniPayment\Client\Model\CreateInvoiceRequest();
            //appid
            $createInvoiceRequest->setAppId($this->config['app_id']);
            //订单金额
            $createInvoiceRequest->setPriceAmount($order['total_amount'] / 100);
            //订单货币单位
            $createInvoiceRequest->setPriceCurrency($this->config['currency']);
            //回调地址
            $createInvoiceRequest->setNotifyUrl($order['notify_url']);
            //返回页地址
            //$createInvoiceRequest->setRedirectUrl($order['return_url']);
            $createInvoiceRequest->setRedirectUrl("https://api.heima2u.com/return_url.php");
            //订单号
            $createInvoiceRequest->setOrderId($order['trade_no']);
            //订单名称
            $createInvoiceRequest->setTitle($this->config['product_name'] ?? (config('v2board.app_name', 'V2Board') . ' - 订阅'));
            //订单详情
            $createInvoiceRequest->setDescription("");
            //语言
            $createInvoiceRequest->setLang($this->config['lang']);

            //创建客户端
            $client = new \UniPayment\Client\UniPaymentClient();
            $client->getConfig()->setClientId($this->config['client_id']);
            $client->getConfig()->setClientSecret($this->config['client_secret']);
            //$client->getConfig()->setIsSandbox(true);
            //$client->getConfig()->setDebug(true);

            //客户端提交订单
            try {
                $response = $client->createInvoice($createInvoiceRequest);
            } catch (GuzzleException $e) {
                abort(500, $e->getMessage());
            } catch (ApiException $e) {
                abort(500, $e->getMessage());
            }

            //检测是否成功
            if($response->getCode() !== "OK"){
                abort(500, $response->getMsg());
            }

            return [
                'type' => 1, // 返回url链接
                'data' => $response->getData()->getInvoiceUrl()
            ];

        }catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
    }

    //当订单状态更改为已付款、已确认和已完成时，IPN（即时付款通知）将发送到 notify_url。
    public function notify($params)
    {
        if($params['status'] !== 'Complete')return false;
        $app_id = $this->config['app_id'];
        $api_key = $this->config['client_secret'];


        $client = new \UniPayment\Client\UniPaymentClient();
        $client->getConfig()->setClientId($app_id);
        $client->getConfig()->setClientSecret($api_key);

        try {
            $response = $client->checkIpn($params);
        } catch (GuzzleException|ApiException $e) {
            return false;
        }

        //检测是否成功
        if($response->getCode() !== "OK"){
            return false;
        }

        return[
            'trade_no' => $params['order_id'],
            'callback_no' => $params['invoice_id']
        ];

    }
}
