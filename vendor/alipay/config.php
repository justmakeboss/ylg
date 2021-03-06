<?php
$config = array (
		//应用ID,您的APPID。
		'app_id' => "2017112800220839",

		//商户私钥，您的原始格式RSA私钥
		'merchant_private_key' =>  str_replace(array("\r\n", "\r", "\n"), '', file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_private_key.pem')) ,
//        'merchant_private_key' =>   file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_private_key.pem') ,
		
		//异步通知地址
		'notify_url' => "http://www.coolpg.cn/home/Notification",
		
		//同步跳转
		'return_url' => "http://www.coolpg.cn/index.php/Mobile/User",

		//编码格式
		'charset' => "UTF-8",

		//签名方式
		'sign_type'=>"RSA2",

		//支付宝网关
		'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

		//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
//		'alipay_public_key' => str_replace(array("\r\n", "\r", "\n"), '', file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_public_key.pem'))  ,
         'alipay_public_key' =>  file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_public_key.pem'),

);
