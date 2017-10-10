<?php

namespace Home\Controller;

use Think\Controller;

class IndexController extends Controller
{
    public function index()
    {
        $this->display();
    }

    public function pay()
    {
        $ip = $this->get_client_ip();
        $parameter = [
            'subject' => '假的描述',
            'total_amount' => 0.01,
            'order_id' => 'TEST'.time(),
            'spbill_create_ip' => $ip
        ];

        $payUrl = $this->wechatPay(C('APPID'), C('MCH_ID'), C('KEY'), $parameter);
        header('location:'.$payUrl);
    }

    public function wechatPay($appid, $mch_id, $key, $data)
    {
        $order_id = $data['order_id']; ////订单号
        $params = [
            'appid' => $appid,
            'mch_id' => $mch_id,
            'body' => $data['subject'],
            'total_fee' => $data['total_amount']*100,
            'out_trade_no' => $order_id,
            'nonce_str' => MD5($order_id),
            'notify_url' => 'http://www.weixin.qq.com/wxpay/pay.php',
            'scene_info' => '{"h5_info": {"type":"Wap","wap_url": "https://pay.qq.com","wap_name": "腾讯充值"}}',
            'spbill_create_ip' => $data['spbill_create_ip'],
            'trade_type' => 'MWEB'
        ];
        ksort($params);
        $signA = '';
        foreach ($params as $k => $value) {
            $signA .= $k.'='.$value.'&';
        }

        $strSignTmp = $signA."key=$key"; //拼接字符串
        $sign = strtoupper(MD5($strSignTmp)); // MD5 后转换成大写
        $params['sign'] = $sign;

        $xml = '<xml>';
        foreach ($params as $k => $val) {
            if (is_numeric($val)) {
                $xml .= '<'.$k.'>'.$val.'</'.$k.'>';
            } else {
                $xml .= '<'.$k.'><![CDATA['.$val.']]></'.$k.'>';
            }
        }
        $xml .= '</xml>';

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";//微信传参地址
        $dataxml = $this->http_post($url, $xml); //后台POST微信传参地址  同时取得微信返回的参数，http_post方法请看下文

        $objectxml = (array)simplexml_load_string($dataxml, 'SimpleXMLElement', LIBXML_NOCDATA); //将微信返回的XML 转换成数组
        if($objectxml['return_code'] == 'SUCCESS')  {
            if($objectxml['result_code'] == 'SUCCESS'){//如果这两个都为此状态则返回mweb_url，详情看‘统一下单’接口文档
                return $objectxml['mweb_url']; //mweb_url是微信返回的支付连接要把这个连接分配到前台
            }
            if($objectxml['result_code'] == 'FAIL'){
                return $err_code_des = $objectxml['err_code_des'];

            }
        }
    }

    public function http_post($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public function get_client_ip($type = 0, $adv=false) {
        $type       =  $type ? 1 : 0;
        static $ip  =   NULL;
        if ($ip !== NULL) return $ip[$type];
        if($adv){
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos    =   array_search('unknown',$arr);
                if(false !== $pos) unset($arr[$pos]);
                $ip     =   trim($arr[0]);
            }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip     =   $_SERVER['HTTP_CLIENT_IP'];
            }elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip     =   $_SERVER['REMOTE_ADDR'];
            }
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u",ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }
}
