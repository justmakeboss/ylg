<?php

namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\DistributLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\CouponLogic;
use app\common\logic\JssdkLogic;
use app\common\model\GoodsConsignment;
use app\common\model\Order;
use app\common\model\Users;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class Member extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            //初始化账户信息
            DistributLogic::rebateDivide($this->user_id);   //初始获取分佣情况

            $this->assign('user', $user); //存储用户信息
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express', 'bind_guide', 'bind_account',
        );
        $is_bind_account = tpCache('basic.is_bind_account');

        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {

                header("location:" . U('Mobile/User/login'));

            exit;
        }

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /**
     * 持有查询
     */
    public function hold_query()
    {

        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $num = 15;
            $goodslist = Db::name('goods')->where("type_id = 6 AND status = 0 AND is_on_sale = 1")->field('goods_id,goods_name')->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $goodslist[$k]['setmeal'] = Db::name('goods_setmeal')->where('goods_id', $v['goods_id'])->select();
            }
            foreach ($goodslist as $key => $val) {
                foreach ($val['setmeal'] as $k => $v) {
                    $goodslist[$key]['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']}")
                        ->group('setmeal_id')
                        ->find();
                }
            }
            $data['data'] = $goodslist;
            $data['code'] = 1;

            return $data;
        } else {
            return $this->fetch('hold_query');
        }
    }


    /**
     * 出售商品
     */
    public function sell_goods()
    {
        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $num = 15;
            $goodslist = Db::name('goods')->where("type_id = 6 AND status = 0 AND is_on_sale = 1")->field('goods_id,goods_name')->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $goodslist[$k]['setmeal'] = Db::name('goods_setmeal')->where('goods_id', $v['goods_id'])->select();
            }
            foreach ($goodslist as $key => $val) {
                foreach ($val['setmeal'] as $k => $v) {
                    $goodslist[$key]['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']} ")
                        ->group('setmeal_id')
                        ->find();
                }
            }
            $data['data'] = $goodslist;
            $data['code'] = 1;
            return $data;
        } else {
            return $this->fetch();
        }
    }

    /**
     * 出售修改
     */
    public function sell_edit()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            if ($data['name'] == '套餐1') {
                $i = 0;
            } elseif ($data['name'] == '套餐2') {
                $i = 1;
            } elseif ($data['name'] == '套餐3') {
                $i = 2;
            }elseif ($data['name'] == '套餐4') {
                $i = 3;
            } else {
                $i = 4;
            }
            $goodslist = Db::name('goods_setmeal')->where("goods_id = {$data['goods_id']}")->select();
            if ($goodslist[$i]) {
//               $goodsnum = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                   ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->select();
                $goodsnum = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                    ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')->group('setmeal_id')->find();
                if (empty($goodsnum)) {
                    return 0;
                } else {
                    return $goodsnum['nums'] - $goodsnum['sel'] - $goodsnum['self'];
                }
            } else {
                return 0;
            }
            return $data;
        } else {
            $id = $this->request->get('id');
            $goodslist = Db::name('goods')->where("type_id = 6 AND goods_id = {$id}")->field('shop_price,goods_id,goods_name')->find();

//            $goodslist['setmeal'] = Db::name('goods_setmeal')->where('goods_id',$goodslist['goods_id'])->select();
//            foreach($goodslist as $key =>$val){
//                foreach($goodslist['setmeal'] as $k =>$v){
//                    $goodslist['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
//                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']}")
//                        ->group('setmeal_id')
//                        ->find();
//                    $name[$v['id']] =  $v['name'];
//
//                }
//            }
//            dump($goodslist);exit;

            $this->assign('goods_list', $goodslist);
//            $this->assign('goodslist',json_encode($name));
            return $this->fetch();
        }


    }

    /**
     * 出售添加
     */
    public function sell_add()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $data['num'] = intval($data['num']);
            if ($data['num'] <= 0) {
                $this->error('出售数量不正确!');
            }
//            if($data['num'] > $data['sell_num']){
//                $this->error('可出售数量不足!');
//            }
            if ($data['goods_id'] == '' || empty($data['goods_id'])) {
                $this->error('恶意操作!');
            }
            if ($data['name'] == '套餐1') {
                $i = 0;
            } elseif ($data['name'] == '套餐2') {
                $i = 1;
            } elseif ($data['name'] == '套餐3') {
                $i = 2;
            } elseif ($data['name'] == '套餐4') {
                $i = 3;
            } elseif ($data['name'] == '套餐5') {
                $i = 4;
            } else {
                $this->error('请选择套餐!');
            }
            $users = Db::name('users')->where('user_id', $this->user_id)->find();
            if (encrypt($data['paypwd']) !== $users['paypwd']) {
                $this->error('请输入正确的安全密码!');
            }
            if(!Db::name('goods')->where("goods_id = {$data['goods_id']} AND status = 0")->find()){
                $this->error('已过寄售时间!');
            }
            $goodslist = Db::name('goods_setmeal')->alias('s')->join('goods g','g.goods_id = s.goods_id')->where("s.goods_id = {$data['goods_id']}")->select();
            if ($goodslist[$i]) {
//               $goodsnum = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                   ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->select();
                $goodsnum = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                    ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->field('g.order_id,sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')->group('setmeal_id')->find();
                if (empty($goodsnum)) {
                    $this->error('出售数量不足!');
                } else {
                    $sell_num = $goodsnum['nums'] - $goodsnum['sel'] - $goodsnum['self']; //可出售数量
                    if ($data['num'] > $sell_num) {
                        $this->error('出售数量不足!');
                    } else {
                        $arr = array(
                            'user_id' => $this->user_id,
                            'setmeal_id' => $goodslist[$i]['id'],
                            'goods_id' => $data['goods_id'],
                            'order_id' => $goodsnum['order_id'],
                            'num' => $data['num'],
                            'surplus_num' => $data['num'],
                            'goods_name' => $goodslist[$i]['goods_name'],
                            'goods_price' => $goodslist[$i]['shop_price'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s')
                        );
                        Db::startTrans();
                        $gcId = Db::name('goods_consignment')->insertGetId($arr);
                        if ($gcId) {

                            $d = array(
                                'user_id' => $this->user_id,
                                'setmeal_id' => $goodslist[$i]['id'],
                                'goods_id' => $data['goods_id'],
                                'order_id' => $goodsnum['order_id'],
                                'num' => $data['num'],
                                'surplus_num' => $data['num'],
                                'goods_name' => $goodslist[$i]['goods_name'],
                                'goods_price' => $goodslist[$i]['shop_price'],
                                'create_time' => time() + (5*86400),
                                'gid' => $gcId,
                            );
                            Db::name('goods_consignments')->insert($d);

                            $result = Db::name('order_goods')->where("order_id = {$goodsnum['order_id']}")->setInc('sell', $data['num']);
                            // $results = Db::name('goods')->where("goods_id = {$data['goods_id']}")->setInc('store_count', $data['num']);
                            if ($result){
                                Db::name('forzen')->where(['order_id'=>$goodsnum['order_id']])->update(['frozen_status'=>0]);
                                Db::commit();
                                $this->success('寄售成功!');
                            } else {
                                Db::rollback();
                                $this->error('寄售失败');
                            }
                        } else {
                            Db::rollback();
                            $this->error('寄售失败');
                        }
                    }
                }
            } else {
                $this->error('出售数量不足!');
            }
        }


    }

    /**
     * 出售列表
     */
    public function sell_list()
    {
        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $num = 15;
//            $goodslist = Db::name('goods_consignment')->alias('c')
//                ->join('goods_order o','o.setmeal_id = c.setmeal_id')
//                ->join('order r','r.order_id = o.order_id')
//                ->join('agent_order a','a.order_id = o.order_id')
//                ->where("user_id = {$this->user_id} ,")->field('shop_price,goods_name')->limit($num*$p,$num*$p+$num)->select();

//            $goodslist = Db::name('agent_order')->alias('a')
//                ->join('order o', 'o.order_id = a.order_id')
//                ->join('order_goods g', 'g.order_id = o.order_id')
//                ->join('goods_consignment c', "c.setmeal_id = g.setmeal_id AND c.user_id = {$this->user_id}")
//                ->where("a.agent_id = {$this->user_id}  AND o.type = 1")->field('c.goods_price,g.goods_id,c.goods_name,g.setmeal_id,c.num,a.sell_num,a.create_time')->limit($num * $p, $num * $p + $num)->select();

$goodslist = Db::name('agent_order')->alias('a')
                ->join('order_goods g', 'g.order_id = a.order_id')
                ->join('goods_consignment c', "c.setmeal_id = g.setmeal_id AND c.user_id = {$this->user_id}")
                ->where("a.agent_id = {$this->user_id}")->group('a.id')->field('a.id,g.order_id,c.goods_price,g.goods_id,c.goods_name,g.setmeal_id,c.num,a.sell_num,a.create_time')->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $goodslist[$k]['create_time'] = date('Y-m-d',strtotime($v['create_time']));
                $setmeal = Db::name('goods_setmeal')->where('goods_id', $v['goods_id'])->select();
                for ($i = 0; $i < 5; $i++) {
                    if ($setmeal[$i]['id'] == $v['setmeal_id']) {
                        $goodslist[$k]['name'] = '套餐' . ($i + 1);
                        break;
                    }
                }
            }

//dump($goodslist);exit;
//            $goodslist = Db::name('agent_order')
//               ->select();

//            foreach ($goodslist as $k => $v){
//                $goodslist[$k]['setmeal'] = Db::name('goods_setmeal')->where('goods_id',$v['goods_id'])->select();
//            }
//            foreach($goodslist as $key =>$val){
//                foreach($val['setmeal'] as $k =>$v){
//                    $goodslist[$key]['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
//                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']} ")
//                        ->group('setmeal_id')
//                        ->find();
//                }
//            }
            $data['data'] = $goodslist;
            $data['code'] = 1;
            return $data;
        } else {
            return $this->fetch();
        }
    }

    /**
     * 购买列表
     */
    function buy_list()
    {
        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $num = 15;
            $goodslist = Db::name('order')->alias('o')->join('order_goods g', 'g.order_id = o.order_id')
                ->where("o.user_id = {$this->user_id} AND o.type = 0")->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $setmeal = Db::name('goods_setmeal')->where('goods_id', $goodslist['goods_id'])->select();
                for ($i = 0; $i < 5; $i++) {
                    if ($setmeal[$i]['id'] == $goodslist['setmeal_id']) {
                        $goodslist[$k]['name'] = '套餐' . ($i + 1);
                        break;
                    }
                }
                $goodslist[$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
            }
            $data['data'] = $goodslist;
            $data['code'] = 1;

            return $data;
        } else {
            return $this->fetch();
        }
    }

    /**
     * 提货列表
     */
    public function mention_list()
    {

        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $num = 15;
//            $goodslist = Db::name('goods_consignment')->alias('c')
//                ->join('goods_order o','o.setmeal_id = c.setmeal_id')
//                ->join('order r','r.order_id = o.order_id')
//                ->join('agent_order a','a.order_id = o.order_id')
//                ->where("user_id = {$this->user_id} ,")->field('shop_price,goods_name')->limit($num*$p,$num*$p+$num)->select();

            $goodslist = Db::name('agent_order')->alias('a')
                ->join('order o', 'o.order_id = a.order_id')
                ->join('order_goods g', 'g.order_id = o.order_id')
                ->join('goods_consignment c', 'c.setmeal_id = g.setmeal_id')
                ->where("c.user_id = {$this->user_id} AND type = 1")->field('goods_price,g.goods_id,goods_name,g.setmeal_id,c.num,a.sell_num,a.create_time')->limit($num * $p, $num * $p + $num)->select();
//            foreach ($goodslist as $k => $v){
//                $goodslist[$k]['setmeal'] = Db::name('goods_setmeal')->where('goods_id',$v['goods_id'])->select();
//            }
//            foreach($goodslist as $key =>$val){
//                foreach($val['setmeal'] as $k =>$v){
//                    $goodslist[$key]['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
//                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']} ")
//                        ->group('setmeal_id')
//                        ->find();
//                }
//            }
            $data['data'] = $goodslist;
            $data['code'] = 1;

            return $data;
        } else {
            return $this->fetch();
        }
    }


    /**
     * 提货办理
     */
    public function mention_goods()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            if ($data['name'] == '套餐1') {
                $i = 0;
            } elseif ($data['name'] == '套餐2') {
                $i = 1;
            } elseif ($data['name'] == '套餐3') {
                $i = 2;
            } elseif ($data['name'] == '套餐4') {
                $i = 3;
            } else {
                $i = 4;
            }
            $goodslist = Db::name('goods_setmeal')->where("goods_id = {$data['goods_id']}")->select();
            if ($goodslist[$i]) {
//               $goodsnum = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                   ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->select();
                $goodsnum = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                    ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                    ->group('setmeal_id')->find();
                if (empty($goodsnum)) {
                    return 0;
                } else {
                    return $goodsnum['nums'] - $goodsnum['sel'] - $goodsnum['self'];
                }
            } else {
                return 0;
            }
            return $data;
        } else {
            $id = $this->request->get('id');
            $address = Db::name('user_address')->where("user_id = {$this->user_id} AND is_default = 1")->find();
            if (empty($address)) {
                $address = Db::name('user_address')->where(['user_id' => $this->user_id])->find();
            }
            $goodslist = Db::name('goods')->where("type_id = 6 AND goods_id = {$id}")->field('shop_price,goods_id,goods_name')->find();

//            $goodslist['setmeal'] = Db::name('goods_setmeal')->where('goods_id',$goodslist['goods_id'])->select();
//            foreach($goodslist as $key =>$val){
//                foreach($goodslist['setmeal'] as $k =>$v){
//                    $goodslist['setmeal'][$k]['num'] = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                        ->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
//                        ->where("o.user_id = {$this->user_id} AND type = 0 AND setmeal_id = {$v['id']}")
//                        ->group('setmeal_id')
//                        ->find();
//                    $name[$v['id']] =  $v['name'];
//
//                }
//            }
//            dump($goodslist);exit;

            $this->assign('goods_list', $goodslist);
            $this->assign('address', $address);
//            $this->assign('goodslist',json_encode($name));
            return $this->fetch();
        }


    }


    //    提货
    public function self_mention()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $data['num'] = intval($data['num']);
            if ($data['num'] <= 0) {
                $this->error('提货数量不正确!');
            }
//            if($data['num'] > $data['sell_num']){
//                $this->error('可出售数量不足!');
//            }
            if ($data['goods_id'] == '' || empty($data['goods_id'])) {
                $this->error('恶意操作!');
            }
            if ($data['name'] == '套餐1') {
                $i = 0;
            } elseif ($data['name'] == '套餐2') {
                $i = 1;
            } elseif ($data['name'] == '套餐3') {
                $i = 2;
            } elseif ($data['name'] == '套餐4') {
                $i = 3;
            } elseif ($data['name'] == '套餐5') {
                $i = 4;
            }else {
                $this->error('请选择提货套餐!');
            }
            $address = Db::name('user_address')->where("user_id = {$this->user_id} AND is_default = 1")->find();
            if (empty($address)) {
                $this->error('请添加地址！');
            }
            $users = Db::name('users')->where('user_id', $this->user_id)->find();
            if (encrypt($data['paypwd']) !== $users['paypwd']) {
                $this->error('请输入正确的安全密码!');
            }
            if(!Db::name('goods')->where("goods_id = {$data['goods_id']} AND status = 0")->find()){
                $this->error('商品已自提!');
            }
            $goodslist = Db::name('goods_setmeal')->where("goods_id = {$data['goods_id']}")->select();
            if ($goodslist[$i]) {
//               $goodsnum = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
//                   ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->select();
                $goodsnum = Db::name('order')->alias('o')->join('order_goods g', 'o.order_id = g.order_id')
                    ->where("setmeal_id = {$goodslist[$i]['id']} AND user_id = {$this->user_id} AND type = 0")->field('g.order_id,sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                    ->group('setmeal_id')->find();
                if (empty($goodsnum)) {
                    $this->error('可提货数量不足!');
                } else {
                    $sell_num = $goodsnum['nums'] - $goodsnum['sel'] - $goodsnum['self']; //可出售数量
                    if ($data['num'] > $sell_num) {
                        $this->error('可提货数量不足!');
                    } else {
                        $orderLogic = new OrderLogic();
                        $arr = array(
                            'user_id' => $this->user_id,
                            'self_mention_sn' => $orderLogic->get_order_sn(),
                            'setmeal_id' => $goodslist[$i]['id'],
                            'goods_id' => $data['goods_id'],
                            'order_id' => $goodsnum['order_id'],
                            'num' => $data['num'],
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                            'consignee' => $address['consignee'], // 收货人
                            'province' => $address['province'],//'省份id',
                            'city' => $address['city'],//'城市id',
                            'district' => $address['district'],//'县',
                            'twon' => $address['twon'],// '街道',
                            'address' => $address['address'],//'详细地址',
                            'mobile' => $address['mobile'],//'手机'
                        );
//                        Db::startTrans();
                        if (Db::name('self_mention_order')->insert($arr)) {
                            if (Db::name('order_goods')->where("order_id = {$goodsnum['order_id']}")->setInc('self_mention', $data['num'])) {
                                Db::name('forzen')->where(['order_id'=>$goodsnum['order_id']])->update(['frozen_status'=>0]);
//                                Db::commit();
                                $this->success('提货成功!');
                            } else {
//                                Db::rollback();
                                $this->error('提货失败');
                            }
                        } else {
//                            Db::rollback();
                            $this->error('提货失败');
                        }
                    }
                }
            } else {
                $this->error('可提货数量不足!');
            }
        }
    }


    /**
     * 提货明细
     */
    function mention_log()
    {
        if ($this->request->isAjax()) {
            $p = $this->request->post('p');
            $act = $this->request->post('act');
            if($act == 'edit'){
                $id = $this->request->post('id');

                if($self = Db::name('self_mention_order')->where("mention_id = $id")->find()){
                    if($self['user_id'] == $this->user_id){
                        Db::name('self_mention_order')->where("mention_id = $id")->update(['status'=>3]);
                    }else{
                        $this->error('异常操作');
                    }
                }else{
                    $this->error('异常操作');
                }
                return true;
            }
            $num = 15;
            $goodslist = Db::name('self_mention_order')->alias('o')->join('goods g', 'g.goods_id = o.goods_id')
                ->where("o.user_id = {$this->user_id}")->field('o.*,g.goods_sn,g.goods_name')->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $setmeal = Db::name('goods_setmeal')->where('goods_id', $goodslist['goods_id'])->select();
                for ($i = 0; $i < 5; $i++) {
                    if ($setmeal[$i]['id'] == $goodslist['setmeal_id']) {
                        $goodslist[$k]['name'] = '套餐' . ($i + 1);
                        break;
                    }
                }
            }
            $data['data'] = $goodslist;
            $data['code'] = 1;

            return $data;
        } else {
            return $this->fetch();
        }
    }

    /**
     * 委托管理
     */
    public function entrust_list()
    {
        if($this->request->isAjax()){
            //删除
            if($this->request->post('act') == 'del'){
                $id = $this->request->post('id');
                if($id){
                    $data = Db::name('goods_consignment')->where("user_id = {$this->user_id} AND id = $id")->find();
                    if(Db::name('goods')->where("goods_id = {$data['goods_id']} AND type_id = 6 AND status = 0")->find()){


                        // $results = Db::name('goods')->where("goods_id = {$data['goods_id']}")->setDec('store_count', $data['surplus_num']);

                        //                    Db::startTrans();
                        $result = Db::name('order_goods')->where("order_id = {$data['order_id']}")->setDec('sell', $data['surplus_num']);
                        if($result){
                            //                        Db::commit();
                            Db::name('goods_consignment')->where("user_id = {$this->user_id} AND id = $id")->delete();
                            Db::name('goods_consignments')->where("user_id = {$this->user_id} AND gid = $id")->delete();
                            $this->success('取消成功');
                        }else{
                            //                        Db::rollback();
                            $this->error('取消失败');
                        }
                    }else{
                        $this->error('正在售卖中，无法取消!');
                    }
                }else{
                    $this->error('取消失败');
                }

            }
            $p = $this->request->post('p');
            $num = 15;
            $goodslist = Db::name('goods_consignment')->where("user_id = {$this->user_id} AND surplus_num > 0")->order('id', 'desc')->limit($num * $p, $num * $p + $num)->select();
            foreach ($goodslist as $k => $v) {
                $d = Db::name('goods_consignments')->where('gid='.$v['id'])->find();;
                $goodslist[$k]['status'] = $d['five_status'] == 0 ? '进行中' :'已完成';
                $goodslist[$k]['create_time'] = date('Y-m-d H:i:s',strtotime($v['create_time']));
                $setmeal = Db::name('goods_setmeal')->where('goods_id', $v['goods_id'])->select();
                for ($i = 0; $i < 5; $i++) {
                    if ($setmeal[$i]['id'] == $v['setmeal_id']) {
                        $goodslist[$k]['name'] = '套餐' . ($i + 1);
                        break;
                    }
                }
            }
            $data['data'] = $goodslist;
            $data['code'] = 1;
            return $data;
        }else{
            return $this->fetch();
        }

    }


    
}
