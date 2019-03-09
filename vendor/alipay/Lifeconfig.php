<?php
$config = array (
		//应用ID,您的APPID。
		'app_id' => "2018110662028400",

		//商户私钥，您的原始格式RSA私钥
		'merchant_private_key' =>  str_replace(array("\r\n", "\r", "\n"), '', file_get_contents( ALIPAYSDK_PATH.'/lifecert/rsa_private_key.pem')) ,
//        'merchant_private_key' =>   file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_private_key.pem') ,
		
		//异步通知地址
		'notify_url' => "http://test.gcgxcs.com//index.php/app/Notification/lifenotify",
		
		//同步跳转
		'return_url' => "http://test.html.gcgxcs.com/html/component/success.html",

		//编码格式
		'charset' => "UTF-8",

		//签名方式
		'sign_type'=>"RSA2",

		//支付宝网关
		'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

		//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
//		'alipay_public_key' => str_replace(array("\r\n", "\r", "\n"), '', file_get_contents( ALIPAYSDK_PATH.'/cert/rsa_public_key.pem'))  ,
         'alipay_public_key' =>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtKvh/nRanBPSov0JbRW/KPm5r80HLqib77Sy0smVqos6S5qlsciwZKF4PNt5Chc/cOBVZiLLG6sSFMeKNfK0FIjo/9uwsRErdXY8mNillf2iUf7eTocfOKqbJDgGNmiR/CuKpZ22rZTDzuOgAjbjfcN1Th0/kXmWLtZBJhxhtPfUDkwm14823uvcWnf74XCSLduXot3x3yqomVNVT3/D4aAtZFxmCzV4hQc8fNQH3Vb9rronESP4OvW0mQGd8tGgi/l8nX9BW8hGfR3oQRFAhg7mzTz971WFz5q9+G7OSMqbsOuB4jnEmIB4QB3NTUAyNgVR1GvhCEzn15QnEzJs4QIDAQAB',

);
