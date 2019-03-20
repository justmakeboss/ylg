<?php

namespace app\mobile\controller;

use think\Request;
use think\Db;
use app\common\logic\OrderLogic;

class Payment extends MobileBase
{

    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if (!empty($pay_radio)) {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        } else // 第三方 支付商返回
        {
            //$_GET = I('get.');
            //file_put_contents('./a.html',$_GET,FILE_APPEND);
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents('php://input');
//        if(empty($this->pay_code))
//            exit('pay_code 不能为空');
        // 导入具体的支付类文件
//        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php
//        $code = '\\'.$this->pay_code; // \alipay
//        $this->payment = new $code();
    }

    public function paypwd()
    {

    }

    /**
     *  提交支付方式
     */
    public function getCode()
    {
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('id/d'); // 订单id
        $num = I('num/d'); // 数量
        $setmeal = I('setmeal/d'); // 套餐id
        $paypwd = I('paypwd'); // 支付密码
        if (!session('user')) $this->error('请先登录', U('User/login'));
        // 修改订单的支付方式
//            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
//            M('order')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
        $order = M('order')->where("order_id", $order_id)->find();
        if (!empty($order_id)) {
            $order_goods = Db::name('order_goods')->where("order_id = {$order['order_id']}")->find();
            $goodss = Db::name('goods')->where("goods_id = {$order_goods['goods_id']}")->find();
            if ($order_goods['goods_num'] > $goodss['store_count']) {
                $this->error('商品库存不足无法支付！');
            }
            // if($goodss['prom_type'] == 1){
            //     $order['shop_integral'] = 0;
            // }
        }
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        if ($order['pay_status'] == 1) {
            $this->error('此订单，已完成支付!');
        }
        if (encrypt($paypwd) != $user['paypwd']) {
            $this->error('安全密码不正确!');
        }
//            if($goodss['prom_type'] == 1){
//                $flash_sale = Db::name('flash_sale')->where("goods_id = {$goodss['goods_id']}")->find();
//                $goods['shop_price'] = $flash_sale['price'];
//            }
/////////////判断开始/////////////////////////////////////////////////////////////////////////////////////////////
        if (empty($order_id)) {
            if (empty($num) || empty($setmeal)) {
                $this->error('异常操作!');
            }
            $setmeal = Db::name('goods_setmeal')->alias('s')->join('goods g', 'g.goods_id = s.goods_id')->where('id', $setmeal)->find();

            $order_num = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                ->where("user_id = {$user['user_id']} AND goods_id = {$setmeal['id']} AND type = 0")->field('sum(goods_num) sums')->find();
            if ($setmeal['stock'] < $num) {
                $this->error('库存数量不足!');
            }
            $order['order_amount'] = $setmeal['trade_price'] * $num;
            $order['quota'] = $setmeal['quota'] * $num;
            $order['type'] = 0;
        } else {
            if ($order['user_id'] != $user['user_id']) {
                $this->error('异常操作!');
            }
        }
        if ($order['order_amount'] > $user['user_money']) {
            $this->error('余额不足,付款失败!');
        }
        if ($order['type'] === 0) {
            if ($order['quota'] > $user['frozen_money']) {
                $this->error('批发票不足,付款失败!');
            }
        }
        if ($order['type'] == 2) {
            //消费积分
            if ($order['shop_integral'] > $user['distribut_money']) {
                $this->error('消费积分不足,付款失败!');
            }
        }

/////////////////判断结束////////////////////////////////////////////////////////////////////////////////////
        //获取配置
        $system = tpCache('ylg_spstem_role');
//        invite_integral 送配额
//        goods_integral  送消费积分
        Db::startTrans();

        switch ($order['type']) {
            case 0:
                /////////////////////批发////////////////////////////////////////////////////////////
                $users = Db::name('users')->where('user_id', $user['user_id'])->find();
                $address = M('UserAddress')->where("user_id", $user['user_id'])->find();
                if (empty($address)) {
                    $this->error('请添加收货地址!');
                }
                $result = Db::name('users')
                    ->where('user_id', $user['user_id'])
                    ->dec('user_money', $order['order_amount'])
                    ->dec('frozen_money', $order['quota'])
                    ->update();
                if(!$result) {Db::rollback();}
                if ($users['user_money'] < 0 || $users['frozen_money'] < 0) {
                    Db::rollback();
                    $this->error('系统繁忙');
                }

                if ($result) {
                    //代理产品套餐表
                    Db::name('goods_setmeal')->where('id', $setmeal['id'])->dec('stock', $num)->update();
                    $setmeal['goods_num'] = $num;
                    $setmeal['goods_price'] = $setmeal['trade_price'];
                    $setmeal['setmeal_id'] = $setmeal['id'];
                    $order_goods = array('0' => $setmeal);
                    $orderLogic = new OrderLogic();
                    $orderLogic->setAction("buy_now");
                    $orderLogic->setCartList($order_goods);

                    $car_price['goodsFee'] = $setmeal['trade_price'];
                    $car_price['quota'] = $order['quota'];
                    $car_price['balance'] = $order['order_amount'];
                    $car_price['balance'] = $order['order_amount'];
                    $car_price['pointsFee'] = 0;
                    $car_price['order_prom_id'] = 0;
                    $car_price['order_prom_amount'] = 0;
                    $car_price['payables'] = $order['order_amount'];
                    $result = $orderLogic->addOrder($user['user_id'], 0, $address['address_id'], 0, 0, 0, $car_price, 0, 0, 0, $start_server_time = 0, $end_server_time = 0, 0); // 添加订单
                    if ($result['status'] == 1) {
                        $order_id = $result['result'];
                        $result = 1;
                    } else {
                        $result = 0;
                    }
                }

                break;


            case 1:
                ////////////////////////活动区//////////////////////////////////////////////////
                $result = Db::name('users')
                    ->where('user_id', $user['user_id'])
                    ->dec('user_money', $order['order_amount'])
                    ->update();
                $d['user_id'] = $user['user_id'];
                $d['reg_time'] = time();
                $d['frozen_dongjie'] = $order['goods_price'] * $system['invite_integral'];
                $d['shifang_time'] = time() + (5 * 86400);
                $d['frozen_status'] = 1;
                $d['order_id'] = $order['order_id'];
                Db::name('forzen')->insertGetId($d);
                if (!$result) {Db::rollback();}
                $order_goods = Db::name('order_goods')->where('order_id', $order['order_id'])->field('goods_id,goods_num,goods_price')->find();
                //获取代理商出售的商品
                $consignment = Db::name('goods_consignment')->where("goods_id = {$order_goods['goods_id']} AND surplus_num>0")->order('create_time')->limit($order_goods['goods_num'])->select();
                $sum = 0; // 累计数量
                minus_stock($order);
                for ($i = 0; $i < count($consignment); $i++) {
                    //寄售人的数量
                    if ($order_goods['goods_num'] >= $consignment[$i]['surplus_num'] + $sum) {
                        $data['surplus_num'] = 0;  //商品剩余数量
                    } else {
                        $data['surplus_num'] = $consignment[$i]['surplus_num'] + $sum - $order_goods['goods_num'];
                    }
                    $sum += $consignment[$i]['surplus_num'];// 累计数量
//                        $data['goods_id'] = $consignment[$i]['id'];  //代理出售商品表id
                    $data['update_time'] = date('Y-m-d H:i:s');  //修改时间
                    $agentdata['agent_id'] = $consignment[$i]['user_id']; // 代理商id  更改代理商金额
                    $agentdata['order_id'] = $consignment[$i]['order_id'];      // 订单ID
                    $agentdata['setmeal_id'] = $consignment[$i]['setmeal_id'];      // 订单ID
                    $agentdata['sell_num'] = $consignment[$i]['surplus_num'] - $data['surplus_num']; // 成交数量
                    $agentdata['create_time'] = date('Y-m-d H:i:s'); // 成交时间
                    Db::name('goods')->where('goods_id',$order_goods['goods_id'])->setDec('store_count',$order_goods['goods_num']);
                    //代理订单表
                    $agentid = Db::name('agent_order')->insertGetId($agentdata);
                    $agents = Db::name('users')->where("user_id = {$agentdata['agent_id']}")->find();
                    //代理寄售表
                    Db::name('goods_consignment')->where("user_id = {$agentdata['agent_id']} AND id = {$consignment[$i]['id']}")->update($data);
                    //剩余数量
                    if ($sum >= $order_goods['goods_num']) {
                        break;
                    }
                }
                //活动区购买升级
                user_upgrade($user['user_id']);
                break;


            case 2:

                //////////自营/////////
                $result = Db::name('users')->where("user_id = {$user['user_id']}")->dec('user_money', $order['order_amount'])->dec('distribut_money', $order['shop_integral'])->update();

                break;

        }


        $results = Db::name('order')->where("order_id = {$order_id}")->save(['pay_status' => 1, 'pay_time' => time()]);
        if ($result && $results) {

            if ($order['type'] == 1) {



            }
            if ($order['type'] == 0) {
                $logs = integrallog($order_id, $user['user_id'], -$order['quota'], 8, $user['frozen_money'], $user['frozen_money'] - $order['quota']);
                $log = balancelog($order_id, $user['user_id'], -$order['order_amount'], 8, $user['user_money'], $user['user_money'] - $order['order_amount']);
            } elseif ($order['type'] == 2) {
                if ($order['shop_integral'] > 0) {
                    $logs = shoppinglog($order_id, $user['user_id'], -$order['shop_integral'], 8, $user['distribut_money'], $user['distribut_money'] - $order['shop_integral']);
                }
                //普通专区购买
                $log = balancelog($order_id, $user['user_id'], -$order['order_amount'], 16, $user['user_money'], $user['user_money'] - $order['order_amount']);
            } else {
                $log = balancelog($order_id, $user['user_id'], -$order['order_amount'], 15, $user['user_money'], $user['user_money'] - $order['order_amount']);
                $logs = integrallog($order_id, $user['user_id'], $order['order_amount'] * $system['invite_integral'], 9, $user['frozen_money'], $user['frozen_money'] + $order['order_amount'] * $system['invite_integral']);;

            }
            if ($log && $logs) {
                Db::commit();
                $this->success('支付成功!');
            } else {
                Db::rollback();
                $this->error('生成日志失败!');
            }
        } else {
            Db::rollback();
            $this->error('支付失败!');
        }
        //订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $payBody = getPayBody($order_id);
        $config_value['body'] = $payBody;
        //微信JS支付  && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')
//           if($this->pay_code == 'weixin' && $_SESSION['openid']){
//               $code_str = $this->payment->getJSAPI($order);
//               exit($code_str);
//           }elseif($this->pay_code == 'miniAppPay'  && $_SESSION['openid']){
//               $code_str = $this->payment->getJSAPI($order);
//               exit($code_str);
//           }else{
//           	    $code_str = $this->payment->get_code($order,$config_value);
//           }

//            $this->assign('code_str', $code_str);
//            $this->assign('order_id', $order_id);
        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    /**
     *  服务订单提交支付方式
     */
    public function getCodeServer()
    {
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); // 订单id
        if (!session('user')) $this->error('请先登录', U('User/login'));
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        M('repair_order')->where("order_id", $order_id)->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
        $order = M('repair_order')->where("order_id", $order_id)->find();
        if ($order['pay_status'] == 1) {
            $this->error('此订单，已完成支付!');
        }
        //订单支付提交
        $pay_radio = 'pay_code=weixin';
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $payBody = '测试商品';
        $config_value['body'] = $payBody;
        //微信JS支付  && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')
        if ($this->pay_code == 'weixin' && $_SESSION['openid']) {
            $code_str = $this->payment->getJSAPI($order);
            exit($code_str);
        } else {
            $code_str = $this->payment->getJSAPI($order);
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        exit;
        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    public function getPay()
    {
        //手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if ($order_id > 0) {
            M('recharge')->where(array('order_id' => $order_id, 'user_id' => $user['user_id']))->save($data);
        } else {
            $data['user_id'] = $user['user_id'];
            $data['nickname'] = $user['nickname'];
            $data['order_sn'] = 'recharge' . get_rand_str(10, 0, 1);
            $data['ctime'] = time();
            $order_id = M('recharge')->add($data);
        }
        if ($order_id) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            if (is_array($order) && $order['pay_status'] == 0) {
                $order['order_amount'] = $order['account'];
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('recharge')->where("order_id", $order_id)->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
                //微信JS支付
                if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                } else {
                    $code_str = $this->payment->get_code($order, $config_value);
                }
            } else {
                $this->error('此充值订单，已完成支付!');
            }
        } else {
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('recharge'); //分跳转 和不 跳转
    }

    public function notifyUrl()
    {
        $this->payment->response();
        exit();
    }

    public function returnUrl()
    {
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
        if (stripos($result['order_sn'], 'recharge') !== false) {
            $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            $this->assign('order', $order);
            if ($result['status'] == 1)
                return $this->fetch('recharge_success');
            else
                return $this->fetch('recharge_error');
            exit();
        }
        $order = M('order')->where("order_sn", $result['order_sn'])->find();
        //预告所获得积分
        $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");


        $this->assign('order', $order);
        $this->assign('point', $points);
        if ($result['status'] == 1)
            return $this->fetch('success');
        else
            return $this->fetch('error');
    }
}
