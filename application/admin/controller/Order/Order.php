<?php
namespace app\admin\controller\Order;
use app\admin\controller\Base;
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Db;

class Order extends Base {
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        C('TOKEN_ON',false); // 关闭表单令牌验证
        $this->order_status = C('ORDER_STATUS');
        $this->pay_status = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
    }

    /*
     *订单首页
     */
    public function index(){
    	$begin = date('Y-m-d',strtotime("-1 year"));//30天前
    	$end = date('Y/m/d',strtotime('+1 days'));

        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $end_time = strtotime(date('Y-m-d 00:00:00',strtotime('+1 days')));

        $total_sales = Db::name('order')->where(['order_status'=>['in',[2,4]]])->sum('order_amount');
        $today_sales = Db::name('order')->where(['order_status'=>['in',[2,4]],'confirm_time'=>['between',$start_time.','.$end_time]])->sum('order_amount');

//        $this->assign('start_time',date('Y-m-d H:i:s',$start_time));
//        $this->assign('end_time',date('Y-m-d H:i:s',$end_time));
        $this->assign('today_sales',$today_sales);
        $this->assign('total_sales',$total_sales);
    	$this->assign('timegap',$begin.'-'.$end);
        return $this->fetch();
    }

    /*
     *Ajax首页
     */
   public function ajaxindex(){
        $orderLogic = new OrderLogic();
        $timegap = I('timegap');
        if($timegap){
        	$gap = explode('-', $timegap);
        	$begin = strtotime($gap[0]);
        	$end = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end = strtotime(I('add_time_end'));
        }

        // 搜索条件
        $condition = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;//收货人

        if($begin && $end){
        	$condition['add_time'] = array('between',"$begin,$end");
        	$where_sum['add_time'] = array('between',"$begin,$end");
        }
        $condition['order_prom_type'] = array('lt',5);

        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;//订单编号
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;//订单

        $order_prom_id = ($keyType && $keyType == 'order_prom_id') ? $keywords : I('order_prom_id') ;
        $order_prom_id ? $condition['mobile'] = trim($order_prom_id) : false;//手机号

        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;//订单状态
        I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;//支付状态
        I('pay_code') != '' ? $condition['type'] = I('pay_code') : false;//订单类型
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;//发货状态
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('order')->where($condition)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        foreach ($orderList as $key => $val){
            if($val['type'] != 2){
                $goods = Db::name('order_goods')->where("order_id = {$val['order_id']}")->find();
            }
            $orderList[$key]['goods_name'] = $goods['goods_name'];
        }
//        dump($orderList);exit;
        $total_sales = Db::name('order')->where(['order_status'=>['in',[2,4]]])->where($where_sum)->sum('order_amount');
        $this->assign('total_sales',$total_sales);
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    //虚拟订单
    public function virtual_list(){
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }
    // 虚拟订单
    public function virtual_info(){
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

    public function virtual_cancel(){
        $order_id = I('order_id/d');
        if(IS_POST){
            $admin_note = I('admin_note');
            $order = M('order')->where(array('order_id'=>$order_id))->find();
            if($order){
                $r = M('order')->where(array('order_id'=>$order_id))->save(array('order_status'=>3,'admin_note'=>$admin_note));
                if($r){
                    $orderLogic = new OrderLogic();
                    $orderLogic->orderActionLog($order_id,$admin_note, '取消订单');
                    $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
                }else{
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'操作失败'));
                }
            }else{
                $this->ajaxReturn(array('status'=>-1,'msg'=>'订单不存在'));
            }
        }
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        $this->assign('order',$order);
        return $this->fetch();
    }

    public function verify_code(){
        if(IS_POST){
            $vr_code = trim(I('vr_code'));
            if (!preg_match('/^[a-zA-Z0-9]{15,18}$/',$vr_code)) {
                $this->ajaxReturn(['status'=>0,'msg' => '兑换码格式错误，请重新输入']);
            }
            $vr_code_info = M('vr_order_code')->where(array('vr_code'=>$vr_code))->find();
            $order = M('order')->where(['order_id'=>$vr_code_info['order_id']])->field('order_status,order_sn,user_note')->find();
            if($order['order_status'] > 1 ){
                $this->ajaxReturn(['status'=>0,'msg' => '兑换码对应订单状态不符合要求']);
            }
            if(empty($vr_code_info)){
                $this->ajaxReturn(['status'=>0,'msg' => '该兑换码不存在']);
            }
            if ($vr_code_info['vr_state'] == '1') {
                $this->ajaxReturn(['status'=>0,'msg' => '该兑换码已被使用']);
            }
            if ($vr_code_info['vr_indate'] < time()) {
                $this->ajaxReturn(['status'=>0,'msg'=>'该兑换码已过期，使用截止日期为： '.date('Y-m-d H:i:s',$vr_code_info['vr_indate'])]);
            }
            if ($vr_code_info['refund_lock'] > 0) {//退款锁定状态:0为正常,1为锁定(待审核),2为同意
                $this->ajaxReturn(['status'=>0,'msg'=> '该兑换码已申请退款，不能使用']);
            }
            $update['vr_state'] = 1;
            $update['vr_usetime'] = time();
            M('vr_order_code')->where(array('vr_code'=>$vr_code))->save($update);
            //检查订单是否完成
            $condition = array();
            $condition['vr_state'] = 0;
            $condition['refund_lock'] = array('in',array(0,1));
            $condition['order_id'] = $vr_code_info['order_id'];
            $condition['vr_indate'] = array('gt',time());
            $vr_order = M('vr_order_code')->where($condition)->select();
            if(empty($vr_order)){
                $data['order_status'] = 2;  //此处不能直接为4，不然前台不能评论
                $data['confirm_time'] = time();
                M('order')->where(['order_id'=>$vr_code_info['order_id']])->save($data);
                M('order_goods')->where(['order_id'=>$vr_code_info['order_id']])->save(['is_send'=>1]);  //把订单状态改为已收货
            }
            $order_goods = M('order_goods')->where(['order_id'=>$vr_code_info['order_id']])->find();
            if($order_goods){
                $result = [
                    'vr_code'=>$vr_code,
                    'order_goods'=>$order_goods,
                    'order'=>$order,
                    'goods_image'=>goods_thum_images($order_goods['goods_id'],240,240),
                ];
                $this->ajaxReturn(['status'=>1,'msg'=>'兑换成功','result'=>$result]);
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'虚拟订单商品不存在']);
            }
        }
        return $this->fetch();
    }

    //虚拟订单临时支付方法，以后要删除
    public function generateVirtualCode(){
        $order_id = I('order_id/d');
        // 获取操作表
        $order = M('order')->where(array('order_id'=>$order_id))->find();
        update_pay_status($order['order_sn'], ['admin_id'=>session('admin_id'),'note'=>'订单付款成功']);
        $vr_order_code = Db::name('vr_order_code')->where('order_id',$order_id)->find();
        if(!empty($vr_order_code)){
            $this->success('成功生成兑换码', U('Order.Order/virtual_info',['order_id'=>$order_id]), 1);
        }else{
            $this->error('生成兑换码失败', U('Order.Order/virtual_info',['order_id'=>$order_id]), 1);
        }
    }

    /*
     * ajax 发货订单列表
    */
    public function ajaxdelivery(){
    	$condition = array();
    	I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
    	I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
    	$shipping_status = I('shipping_status');
    	$condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
        $condition['order_status'] = array('in','1,2,4');
    	$count = M('order')->where($condition)->count();
    	$Page  = new AjaxPage($count,10);
    	//搜索条件下 分页赋值
    	foreach($condition as $key=>$val) {
            if(!is_array($val)){
                $Page->parameter[$key]   =   urlencode($val);
            }
    	}
    	$show = $Page->show();
    	$orderList = M('order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('add_time DESC')->select();
    	$this->assign('orderList',$orderList);
    	$this->assign('page',$show);// 赋值分页输出
    	$this->assign('pager',$Page);
    	return $this->fetch();
    }

    public function refund_order_list(){
    	$orderLogic = new OrderLogic();
    	$condition = array();
    	I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
    	I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
    	$condition['shipping_status'] = 0;
    	$condition['order_status'] = 3;
    	$condition['pay_status'] = array('gt',0);
    	$count = M('order')->where($condition)->count();
    	$Page  = new Page($count,10);
    	//搜索条件下 分页赋值
    	foreach($condition as $key=>$val) {
    		if(!is_array($val)){
    			$Page->parameter[$key]   =   urlencode($val);
    		}
    	}
    	$show = $Page->show();
    	$orderList = M('order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('add_time DESC')->select();
    	$this->assign('orderList',$orderList);
    	$this->assign('page',$show);// 赋值分页输出
    	$this->assign('pager',$Page);
    	return $this->fetch();
    }

    public function refund_order_info($order_id){
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	$orderGoods = $orderLogic->getOrderGoods($order_id);
    	$this->assign('order',$order);
    	$this->assign('orderGoods',$orderGoods);
    	return $this->fetch();
    }

    //取消订单退款原路退回
    public function refund_order(){
        $data = I('post.');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($data['order_id']);
        if(!order){
            $this->error('订单不存在或参数错误');
        }
        if($data['pay_status'] == 3){
            if($data['refund_type'] == 1){
            	//取消订单退款退余额
                if(updateRefundOrder($order,1)){
                    $this->success('成功退款到账户余额');
                }else{
                    $this->error('退款失败');
                }
            }
            if($data['refund_type']== 0){
                //取消订单支付原路退回
                if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'alipay' || $order['pay_code'] == 'alipayMobile'){
		header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
                }else{
                    $this->error('该订单支付方式不支持在线退回');
                }
            }
        }else{
            M('order')->where(array('order_id'=>$order['order_id']))->save($data);
            $this->success('拒绝退款操作成功');
        }
    }

    /**
     * 订单详情
     * @param int $order_id 订单id
     * @return mixed
     */
    public function detail($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $button = $orderLogic->getOrderButton($order);
        // 获取操作记录
        $action_log = M('order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $has_user = false;
        $adminIds = [];
        //查找用户昵称
        foreach ($action_log as $k => $v){
            if ($v['action_user']) {
                $adminIds[$k] = $v['action_user'];
            } else {
                $has_user = true;
            }
        }
        if($adminIds && count($adminIds) > 0){
            $admins = M("admin")->where("admin_id in (".implode(",",$adminIds).")")->getField("admin_id , user_name", true);
        }
        if($has_user){
            $user = M("users")->field('user_id,nickname')->where(['user_id'=>$order['user_id']])->find();
        }
    	$this->assign('admins',$admins);
        $this->assign('user', $user);
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderGoods',$orderGoods);
        $split = count($orderGoods) >1 ? 1 : 0;
        foreach ($orderGoods as $val){
        	if($val['goods_num']>1){
        		$split = 1;
        	}
        }
        $this->assign('split',$split);
        $this->assign('button',$button);
        return $this->fetch();
    }

    /**
     * 订单详情
     * @param int $order_id 订单id
     * @return mixed
     */
    public function details($order_id){
        $order = Db::name('self_mention_order')->alias('o')->join('goods g','o.goods_id = g.goods_id')->field('g.*,o.*,o.status statuss')->where("mention_id = $order_id")->find();
        $orderLogic = new OrderLogic();
        $order['address2'] = $orderLogic->getAddressName($order['province'],$order['city'],$order['district']);
        $order['address2'] = $order['address2'].$order['address'];
//        $orderGoods = $orderLogic->getOrderGoods($order_id);
//        $button = $orderLogic->getOrderButton($order);
        // 获取发货记录
//        $action_log = M('delivery_doc')->where(array('mention_id'=>$order_id))->order('create_time desc')->find();
//        $has_user = false;
//        $adminIds = [];
//        //查找用户昵称
//        foreach ($action_log as $k => $v){
//            if ($v['action_user']) {
//                $adminIds[$k] = $v['action_user'];
//            } else {
//                $has_user = true;
//            }
//        }
//        dump($action_log);exit;
//        if($action_log && count($action_log) > 0){
//            $admins = M("admin")->where("admin_id in ({$action_log['admin_id']})")->getField("admin_id , user_name", true);
//        }
        if($order['user_id']){
            $user = M("users")->field('user_id,nickname')->where(['user_id'=>$order['user_id']])->find();
        }
        if($order['status'] == 1){
            $button = ['delivery'=>'去发货'];
        }
//        dump($order);exit;
//        $btn['delivery'] = '去发货';
//        $this->assign('admins',$admins);
        $this->assign('user', $user);
        $this->assign('order',$order);
//        $this->assign('action_log',$action_log);
//        $this->assign('orderGoods',$orderGoods);
//        $split = count($orderGoods) >1 ? 1 : 0;
//        foreach ($orderGoods as $val){
//            if($val['goods_num']>1){
//                $split = 1;
//            }
//        }
//        $this->assign('split',$split);
        $this->assign('button',$button);
        return $this->fetch();
    }

    /**
     * 订单编辑
     * @param int $id 订单id
     */
    public function edit_order(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }

        $orderGoods = $orderLogic->getOrderGoods($order_id);

       	if(IS_POST)
        {
            $order['consignee'] = I('consignee');// 收货人
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机
            $order['invoice_title'] = I('invoice_title');// 发票
            $order['admin_note'] = I('admin_note'); // 管理员备注
            $order['admin_note'] = I('admin_note'); //
            $order['shipping_code'] = I('shipping');// 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');
            $order['pay_code'] = I('payment');// 支付方式
            $order['pay_name'] = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');
            $goods_id_arr = I("goods_id/a");
            $new_goods = $old_goods_arr = array();
            //################################订单添加商品
            if($goods_id_arr){
            	$new_goods = $orderLogic->get_spec_goods($goods_id_arr);
            	foreach($new_goods as $key => $val)
            	{
            		$val['order_id'] = $order_id;
            		$rec_id = M('order_goods')->add($val);//订单添加商品
            		if(!$rec_id)
            			$this->error('添加失败');
            	}
            }

            //################################订单修改删除商品
            $old_goods = I('old_goods/a');
            foreach ($orderGoods as $val){
            	if(empty($old_goods[$val['rec_id']])){
            		M('order_goods')->where("rec_id=".$val['rec_id'])->delete();//删除商品
            	}else{
            		//修改商品数量
            		if($old_goods[$val['rec_id']] != $val['goods_num']){
            			$val['goods_num'] = $old_goods[$val['rec_id']];
            			M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
            		}
            		$old_goods_arr[] = $val;
            	}
            }

            $goodsArr = array_merge($old_goods_arr,$new_goods);
            $result = calculate_price($order['user_id'],$goodsArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
            if($result['status'] < 0)
            {
            	$this->error($result['msg']);
            }

            //################################修改订单费用
            $order['goods_price']    = $result['result']['goods_price']; // 商品总价
            $order['shipping_price'] = $result['result']['shipping_price'];//物流费
            $order['order_amount']   = $result['result']['order_amount']; // 应付金额
            $order['total_amount']   = $result['result']['total_amount']; // 订单总价
            $o = M('order')->where('order_id='.$order_id)->save($order);

            $l = $orderLogic->orderActionLog($order_id,'修改订单','修改订单');//操作日志
            if($o && $l){
            	$this->success('修改成功',U('Admin/Order.Order/editprice',array('order_id'=>$order_id)));
            }else{
            	$this->success('修改失败',U('Admin/Order.Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        // 获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //获取支付方式
        $payment_list = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        //获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();

        $this->assign('order',$order);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('orderGoods',$orderGoods);
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        return $this->fetch();
    }

    /*
     * 拆分订单
     */
    public function split_order(){
    	$order_id = I('order_id');
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	if($order['shipping_status'] != 0){
    		$this->error('已发货订单不允许编辑');
    		exit;
    	}
    	$orderGoods = $orderLogic->getOrderGoods($order_id);
    	if(IS_POST){
    		$data = I('post.');
    		//################################先处理原单剩余商品和原订单信息
    		$old_goods = I('old_goods/a');

    		foreach ($orderGoods as $val){
    			if(empty($old_goods[$val['rec_id']])){
    				M('order_goods')->where("rec_id=".$val['rec_id'])->delete();//删除商品
    			}else{
    				//修改商品数量
    				if($old_goods[$val['rec_id']] != $val['goods_num']){
    					$val['goods_num'] = $old_goods[$val['rec_id']];
    					M('order_goods')->where("rec_id=".$val['rec_id'])->save(array('goods_num'=>$val['goods_num']));
    				}
    				$oldArr[] = $val;//剩余商品
    			}
    			$all_goods[$val['rec_id']] = $val;//所有商品信息
    		}
    		$result = calculate_price($order['user_id'],$oldArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
    		if($result['status'] < 0)
    		{
    			$this->error($result['msg']);
    		}
    		//修改订单费用
    		$res['goods_price']    = $result['result']['goods_price']; // 商品总价
    		$res['order_amount']   = $result['result']['order_amount']; // 应付金额
    		$res['total_amount']   = $result['result']['total_amount']; // 订单总价
    		M('order')->where("order_id=".$order_id)->save($res);
			//################################原单处理结束

    		//################################新单处理
    		for($i=1;$i<20;$i++){
                $temp = $this->request->param($i.'_old_goods/a');
    			if(!empty($temp)){
    				$split_goods[] = $temp;
    			}
    		}

    		foreach ($split_goods as $key=>$vrr){
    			foreach ($vrr as $k=>$v){
    				$all_goods[$k]['goods_num'] = $v;
    				$brr[$key][] = $all_goods[$k];
    			}
    		}

    		foreach($brr as $goods){
    			$result = calculate_price($order['user_id'],$goods,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0);
    			if($result['status'] < 0)
    			{
    				$this->error($result['msg']);
    			}
    			$new_order = $order;
    			$new_order['order_sn'] = date('YmdHis').mt_rand(1000,9999);
    			$new_order['parent_sn'] = $order['order_sn'];
    			//修改订单费用
    			$new_order['goods_price']    = $result['result']['goods_price']; // 商品总价
    			$new_order['order_amount']   = $result['result']['order_amount']; // 应付金额
    			$new_order['total_amount']   = $result['result']['total_amount']; // 订单总价
    			$new_order['add_time'] = time();
    			unset($new_order['order_id']);
    			$new_order_id = DB::name('order')->insertGetId($new_order);//插入订单表
    			foreach ($goods as $vv){
    				$vv['order_id'] = $new_order_id;
    				unset($vv['rec_id']);
    				$nid = M('order_goods')->add($vv);//插入订单商品表
    			}
    		}
    		//################################新单处理结束
    		$this->success('操作成功',U('Admin/Order/detail',array('order_id'=>$order_id)));
            exit;
    	}

    	foreach ($orderGoods as $val){
    		$brr[$val['rec_id']] = array('goods_num'=>$val['goods_num'],'goods_name'=>getSubstr($val['goods_name'], 0, 35).$val['spec_key_name']);
    	}
    	$this->assign('order',$order);
    	$this->assign('goods_num_arr',json_encode($brr));
    	$this->assign('orderGoods',$orderGoods);
    	return $this->fetch();
    }

    /*
     * 价钱修改
     */
    public function editprice($order_id){
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $this->editable($order);
        if(IS_POST){
        	$admin_id = session('admin_id');
            if(empty($admin_id)){
                $this->error('非法操作');
                exit;
            }
            $update['discount'] = I('post.discount');
            $update['shipping_price'] = I('post.shipping_price');
			$update['order_amount'] = $order['goods_price'] + $update['shipping_price'] - $update['discount'] - $order['user_money'] - $order['integral_money'] - $order['coupon_price'];
            $row = M('order')->where(array('order_id'=>$order_id))->save($update);
            if(!$row){
                $this->success('没有更新数据',U('Admin/Order.Order/editprice',array('order_id'=>$order_id)));
            }else{
                $this->success('操作成功',U('Admin/Order.Order/detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        $this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 订单删除
     * @param int $id 订单id
     */
    public function delete_order(){
        $order_id = I('post.order_id/d',0);
    	$orderLogic = new OrderLogic();
    	$del = $orderLogic->delOrder($order_id);
        $this->ajaxReturn($del);
    }

    /**
     * 订单取消付款
     * @param $order_id
     * @return mixed
     */
    public function pay_cancel($order_id){
    	if(I('remark')){
    		$data = I('post.');
    		$note = array('退款到用户余额','已通过其他方式退款','不处理，误操作项');
    		if($data['refundType'] == 0 && $data['amount']>0){
    			accountLog($data['user_id'], $data['amount'], 0,  '退款到用户余额');
    		}
    		$orderLogic = new OrderLogic();
            $orderLogic->orderProcessHandle($data['order_id'],'pay_cancel');
    		$d = $orderLogic->orderActionLog($data['order_id'],'支付取消',$data['remark'].':'.$note[$data['refundType']]);
    		if($d){
    			exit("<script>window.parent.pay_callback(1);</script>");
    		}else{
    			exit("<script>window.parent.pay_callback(0);</script>");
    		}
    	}else{
    		$order = M('order')->where("order_id=$order_id")->find();
    		$this->assign('order',$order);
    		return $this->fetch();
    	}
    }

    /**
     * 订单打印
     * @param int $id 订单id
     */
    public function order_print(){
    	$order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $order['full_address'] = $order['province'].' '.$order['city'].' '.$order['district'].' '. $order['address'];
        $orderGoods = $orderLogic->getOrderGoods($order_id);
        $shop = tpCache('shop_info');
        $this->assign('order',$order);
        $this->assign('shop',$shop);
        $this->assign('orderGoods',$orderGoods);
        $template = I('template','print');
        return $this->fetch($template);
    }

    /**
     * 快递单打印
     */
    public function shipping_print(){
        $order_id = I('get.order_id');
        $orderLogic = new OrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        //查询是否存在订单及物流
        $shipping = M('plugin')->where(array('code'=>$order['shipping_code'],'type'=>'shipping'))->find();
        if(!$shipping){
        	$this->error('物流插件不存在');
        }
		if(empty($shipping['config_value'])){
			$this->error('请设置'.$shipping['name'].'打印模板');
		}
        $shop = tpCache('shop_info');//获取网站信息
        $shop['province'] = empty($shop['province']) ? '' : getRegionName($shop['province']);
        $shop['city'] = empty($shop['city']) ? '' : getRegionName($shop['city']);
        $shop['district'] = empty($shop['district']) ? '' : getRegionName($shop['district']);

        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        if(empty($shipping['config'])){
        	$config = array('width'=>840,'height'=>480,'offset_x'=>0,'offset_y'=>0);
        	$this->assign('config',$config);
        }else{
        	$this->assign('config',unserialize($shipping['config']));
        }
        $template_var = array("发货点-名称", "发货点-联系人", "发货点-电话", "发货点-省份", "发货点-城市",
        		 "发货点-区县", "发货点-手机", "发货点-详细地址", "收件人-姓名", "收件人-手机", "收件人-电话",
        		"收件人-省份", "收件人-城市", "收件人-区县", "收件人-邮编", "收件人-详细地址", "时间-年", "时间-月",
        		"时间-日","时间-当前日期","订单-订单号", "订单-备注","订单-配送费用");
        $content_var = array($shop['store_name'],$shop['contact'],$shop['phone'],$shop['province'],$shop['city'],
        	$shop['district'],$shop['phone'],$shop['address'],$order['consignee'],$order['mobile'],$order['phone'],
        	$order['province'],$order['city'],$order['district'],$order['zipcode'],$order['address'],date('Y'),date('M'),
        	date('d'),date('Y-m-d'),$order['order_sn'],$order['admin_note'],$order['shipping_price'],
        );
        $shipping['config_value'] = str_replace($template_var,$content_var, $shipping['config_value']);
        $this->assign('shipping',$shipping);
        return $this->fetch("Plugin/print_express");
    }

    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $orderLogic = new OrderLogic();
		$data = I('post.');
		$res = $orderLogic->deliveryHandle($data);
		if($res){
			$this->success('操作成功',U('Admin/Order.Order/delivery_info',array('order_id'=>$data['order_id'])));
		}else{
			$this->success('操作失败',U('Admin/Order.Order/delivery_info',array('order_id'=>$data['order_id'])));
		}
    }
    /**
     * 生成代理发货单
     */
    public function deliveryHandles(){
        $orderLogic = new OrderLogic();
        $data = I('post.');
        $order = Db::name('self_mention_order')->alias('o')->join('goods g','o.goods_id = g.goods_id')->where("mention_id = {$data['order_id']}")->find();
        $data['order_sn'] = $order['self_mention_sn'];
        $data['mention_id'] =$data['order_id'];
        $data['delivery_sn'] = $orderLogic->get_delivery_sn();
        $data['zipcode'] = $order['zipcode'];
        $data['user_id'] = $order['user_id'];
        $data['admin_id'] = session('admin_id');
        $data['consignee'] = $order['consignee'];
        $data['mobile'] = $order['mobile'];
        $data['country'] = 0;
        $data['province'] = $order['province'];
        $data['city'] = $order['city'];
        $data['district'] = $order['district'];
        $data['address'] = $order['address'];
        $data['shipping_price'] = $order['shipping_price'];
        $data['create_time'] = time();
        $did = M('delivery_doc')->add($data);
        if($did){
            Db::name('self_mention_order')->where("mention_id = {$data['order_id']}")->update(['status'=>2]);
            $this->success('操作成功',U('Admin/Order.Order/agent_order'));
        }else{
            $this->success('操作失败',U('Admin/Order.Order/agent_order'));
        }
    }


    public function delivery_info(){
    	$order_id = I('order_id');
    	$orderLogic = new OrderLogic();
    	$order = $orderLogic->getOrderInfo($order_id);
    	$orderGoods = $orderLogic->getOrderGoods($order_id,2);
        if(!$orderGoods)$this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货
		$delivery_record = M('delivery_doc')->alias('d')->join('__ADMIN__ a','a.admin_id = d.admin_id')->where('d.order_id='.$order_id)->select();
		if($delivery_record){
			$order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
		}
		$this->assign('order',$order);
		$this->assign('orderGoods',$orderGoods);
		$this->assign('delivery_record',$delivery_record);//发货记录
		$shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();
		$this->assign('shipping_list',$shipping_list);
    	return $this->fetch();
    }

    public function delivery_infos(){
        $order_id = I('order_id');
        $order = Db::name('self_mention_order')->where("mention_id = $order_id")->update(['status'=>2]);
        if($order){
            $this->success('操作成功',U('Admin/Order.Order/agent_order'));
        }else{
            $this->success('操作失败',U('Admin/Order.Order/agent_order'));
        }
//        $orderLogic = new OrderLogic();
//        $order = $orderLogic->getOrderInfo($order_id);
//        $orderGoods = $orderLogic->getOrderGoods($order_id,2);
//        if(!$orderGoods)$this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货
        $delivery_record = M('delivery_doc')->alias('d')->join('__ADMIN__ a','a.admin_id = d.admin_id')->where('d.mention_id='.$order_id)->select();
        if($delivery_record){
            $order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
        }
        $this->assign('order',$order);
//        $this->assign('orderGoods',$orderGoods);
        $this->assign('delivery_record',$delivery_record);//发货记录
//        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();
//        $this->assign('shipping_list',$shipping_list);
        return $this->fetch();
    }

    /**
     * 发货单列表
     */
    public function delivery_list(){
        return $this->fetch();
    }


    /**
     * 删除某个退换货申请
     */
    public function return_del(){
        $id = I('get.id');
        M('return_goods')->where("id = $id")->delete();
        $this->success('成功删除!');
    }

    /**
     * 退换货操作
     */
    public function return_info()
    {
        $orderLogic = new OrderLogic();
        $return_id = I('id');
        $return_goods = M('return_goods')->where("id= $return_id")->find();
        if(!$return_goods)
        {
            $this->error('非法操作!');
            exit;
        }
        $user = M('users')->where("user_id = {$return_goods[user_id]}")->find();
        $goods = M('goods')->where("goods_id = {$return_goods[goods_id]}")->find();
        $type_msg = array('仅退款','退货退款','换货');
        $status_msg = C('REFUND_STATUS');
        if(IS_POST)
        {
            $data = I('post.');
            if($return_goods['type'] == 2 && $return_goods['is_receive'] == 1){
            	$data['seller_delivery']['express_time'] = date('Y-m-d H:i:s');
            	$data['seller_delivery'] = serialize($data['seller_delivery']); //换货的物流信息
            }
            $note ="退换货:{$type_msg[$return_goods['type']]}, 状态:{$status_msg[$data['status']]},处理备注：{$data['remark']}";
            $result = M('return_goods')->where("id= $return_id")->save($data);
            if($result && $data['status']==1)
            {
                //审核通过才更改订单商品状态，进行退货，退款时要改对应商品修改库存
                $order = \app\common\model\Order::get($return_goods['order_id']);
                $commonOrderLogic = new \app\common\logic\OrderLogic();
                $commonOrderLogic->alterReturnGoodsInventory($order,$return_goods['rec_id']); //审核通过，恢复原来库存
                if($return_goods['type'] < 2){
                    $orderLogic->disposereRurnOrderCoupon($return_goods); // 是退货可能要处理优惠券
                }
            }
            $orderLogic->orderActionLog($return_goods['order_id'],'退换货',$note);
            $this->success('修改成功!');
            exit;
        }
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if($return_goods['imgs']) $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $this->assign('id',$return_id); // 用户
        $this->assign('user',$user); // 用户
        $this->assign('goods',$goods);// 商品
        $this->assign('return_goods',$return_goods);// 退换货
        $order = M('order')->where(array('order_id'=>$return_goods['order_id']))->find();
        $this->assign('order',$order);//退货订单信息
        return $this->fetch();
    }

    //售后退款原路退回
    public function refund_back(){
    	$return_id = I('id');
        $return_goods = M('return_goods')->where("id= $return_id")->find();
    	$rec_goods = M('order_goods')->where(array('order_id'=>$return_goods['order_id'],'goods_id'=>$return_goods['goods_id']))->find();
    	$order = M('order')->where(array('order_id'=>$rec_goods['order_id']))->find();
    	if($order['pay_code'] == 'weixin' || $order['pay_code'] == 'alipay' || $order['pay_code'] == 'alipayMobile'){
    		$orderLogic = new OrderLogic();
    		$return_goods['refund_money'] = $orderLogic->getRefundGoodsMoney($return_goods);
    		if($order['pay_code'] == 'weixin'){
    			include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
    			$payment_obj =  new \weixin();
    			$data = array('transaction_id'=>$order['transaction_id'],'total_fee'=>$order['order_amount'],'refund_fee'=>$return_goods['refund_money']);
    			$result = $payment_obj->payment_refund($data);
    			if($result['return_code'] == 'SUCCESS'  && $result['result_code' == 'SUCCESS']){
    				updateRefundGoods($return_goods['rec_id']);//订单商品售后退款
    				$this->success('退款成功');
    			}else{
    				$this->error($result['return_msg']);
    			}
    		}else{
    			include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
    			$payment_obj = new \alipay();
    			$detail_data = $order['transaction_id'].'^'.$return_goods['refund_money'].'^'.'用户申请订单退款';
    			$data = array('batch_no'=>date('YmdHi').$rec_goods['rec_id'],'batch_num'=>1,'detail_data'=>$detail_data);
    			$payment_obj->payment_refund($data);
    		}
    	}else{
    		$this->error('该订单支付方式不支持在线退回');
    	}
    }

    /**
     * 退货，余额+积分支付
     * 有用三方金额支付的不走这个方法
     */
    public function refund_balance(){
		$data = I('POST.');
		$return_goods = M('return_goods')->where(array('rec_id'=>$data['rec_id']))->find();
		if(empty($return_goods)) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
		M('return_goods')->where(array('rec_id'=>$data['rec_id']))->save($data);
		updateRefundGoods($data['rec_id'],1);//售后商品退款
		$this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/Order.Order/return_list")]);

    }

    /**
     * 管理员生成申请退货单
     */
    public function add_return_goods()
   {
            $order_id = I('order_id');
            $goods_id = I('goods_id');

            $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id")->find();
            if(!empty($return_goods))
            {
                $this->error('已经提交过退货申请!',U('Admin/Order.Order/return_list'));
                exit;
            }
            $order = M('order')->where("order_id = $order_id")->find();

            $data['order_id'] = $order_id;
            $data['order_sn'] = $order['order_sn'];
            $data['goods_id'] = $goods_id;
            $data['addtime'] = time();
            $data['user_id'] = $order[user_id];
            $data['remark'] = '管理员申请退换货'; // 问题描述
            M('return_goods')->add($data);
            $this->success('申请成功,现在去处理退货',U('Admin/Order.Order/return_list'));
            exit;
    }

    /**
     * 订单操作
     * @param $id
     */
    public function order_action(){
        $orderLogic = new OrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){
            if($action !=='pay'){
                $convert_action= C('CONVERT_ACTION')["$action"];
                $res = $orderLogic->orderActionLog($order_id,$convert_action,I('note'));
            }
        	 $a = $orderLogic->orderProcessHandle($order_id,$action,array('note'=>I('note'),'admin_id'=>0));
            if($res !== false && $a !== false){
                 if ($action == 'remove') {
                     exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/Order.Order/index')))));
                 }
        	 	exit(json_encode(array('status' => 1,'msg' => '操作成功')));
        	 }else{
                 if ($action == 'remove') {
                     exit(json_encode(array('status' => 0, 'msg' => '操作失败', 'data' => array('url' => U('admin/Order.Order/index')))));
                 }
        	 	exit(json_encode(array('status' => 0,'msg' => '操作失败')));
        	 }
        }else{
        	$this->error('参数错误',U('Admin/Order.Order/detail',array('order_id'=>$order_id)));
        }
    }

    public function order_log(){
    	$timegap = urldecode(I('timegap'));
    	if($timegap){
    		$gap = explode('-', $timegap);
            $timegap_begin = $gap;
            $timegap_end = $gap[1];
    		$begin = strtotime($timegap_begin);
    		$end = strtotime($timegap_end);
    	}else{
    	    //@new 兼容新模板
            $timegap_begin = urldecode(I('timegap_begin'));
            $timegap_end = urldecode(I('timegap_end'));
    	    $begin = strtotime($timegap_begin);
    	    $end = strtotime($timegap_end);
    	}
    	$condition = array();
    	$log =  M('order_action');
    	if($begin && $end){
    		$condition['log_time'] = array('between',"$begin,$end");
    	}
    	$admin_id = I('admin_id');
		if($admin_id >0 ){
			$condition['action_user'] = $admin_id;
		}
    	$count = $log->where($condition)->count();
    	$Page = new Page($count,20);

    	foreach($condition as $key=>$val) {
    		$Page->parameter[$key] = urlencode($begin.'_'.$end);
    	}
    	$show = $Page->show();
    	$list = $log->where($condition)->order('action_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $orderIds = [];
        foreach ($list as $log) {
            if (!$log['action_user']) {
                $orderIds[] = $log['order_id'];
            }
        }
        if ($orderIds) {
            $users = M("users")->alias('u')->join('__ORDER__ o', 'o.user_id = u.user_id')->getField('o.order_id,u.nickname');
        }
        $this->assign('timegap_begin',$timegap_begin);
        $this->assign('timegap_end',$timegap_end);
        $this->assign('users',$users);
    	$this->assign('list',$list);
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);
    	$admin = M('admin')->getField('admin_id,user_name');
    	$this->assign('admin',$admin);
    	return $this->fetch();
    }

    /**
     * 检测订单是否可以编辑
     * @param $order
     */
    private function editable($order){
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }
        return;
    }

    public function export_order()
    {
    	//搜索条件
        $consignee = I('consignee');
        $order_sn =  I('order_sn');
        $timegap = I('timegap');
        $order_status = I('order_status');
        $order_ids = I('order_ids');
        $order_prom_id =I('order_prom_id') ;
        $type = I('pay_code');
        $shipping_status = I('shipping_status');

        // I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;//订单状态
        // I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;//支付状态
        // I('pay_code') != '' ? $condition['type'] = I('pay_code') : false;//订单类型
        $where = array();//搜索条件
        if($consignee){
            $where['consignee'] = ['like','%'.$consignee.'%'];
        }
        if(I('pay_status')){
            $where['pay_status'] = I('pay_status');
        }
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($order_prom_id){
            $where['mobile'] = $order_prom_id;
        }
        if($shipping_status){
            $where['shipping_status'] = $shipping_status;
        }
        if($type){
            $where['type'] = $type;
        }
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap);
            $end = strtotime($gap[1]);
            $where['add_time'] = ['between',[$begin, $end]];
        }
        if(I('add_time_begin') && I('add_time_end')){
            $begin = strtotime(I('add_time_begin'));
            $end = strtotime(I('add_time_end'));
            $where['add_time'] = ['between',[$begin, $end]];
        }

        if($order_ids){
            $where['order_id'] = ['in', $order_ids];
        }
        $orderList = Db::name('order')->field("*,FROM_UNIXTIME(add_time,'%Y-%m-%d %H-%i-%s') as create_time")->where($where)->order('order_id')->select();
    	$strTable ='<table width="500" border="1">';
    	$strTable .= '<tr>';
    	$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">日期</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单金额</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">实际支付</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品信息</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单类型</td>';
    	$strTable .= '</tr>';
	    if(is_array($orderList)){
	    	$region	= get_region_list();
	    	foreach($orderList as $k=>$val){
	    		$strTable .= '<tr>';
	    		$strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].' </td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".' </td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$this->pay_status[$val['pay_status']].'</td>';
	    		$strTable .= '<td style="text-align:left;font-size:12px;">'.$this->shipping_status[$val['shipping_status']].'</td>';
	    		$orderGoods = D('order_goods')->where('order_id='.$val['order_id'])->select();
	    		$strGoods="";
                $goods_num = 0;
	    		foreach($orderGoods as $goods){
                    $goods_num = $goods_num + $goods['goods_num'];
	    			$strGoods .= "商品编号：".$goods['goods_sn']." 商品名称：".$goods['goods_name'];
	    			if ($goods['spec_key_name'] != '') $strGoods .= " 规格：".$goods['spec_key_name'];
	    			$strGoods .= "<br />";
	    		}
                // 0：批发  1：零售 2：自营 
	    		unset($orderGoods);
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$goods_num.' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$strGoods.' </td>';
                if ($val['type'] == 0 ) {
                    $strTable .= '<td style="text-align:left;font-size:12px;">代理</td>';
                    # code...
                }else if($val['type'] == 1){
                    $strTable .= '<td style="text-align:left;font-size:12px;">零售</td>'; 
                }else{
                    $strTable .= '<td style="text-align:left;font-size:12px;">自营 </td>'; 
                }
	    		
	    		$strTable .= '</tr>';
	    	}
	    }
    	$strTable .='</table>';
    	unset($orderList);
    	downloadExcel($strTable,'order');
    	exit();
    }

    public function export_orders()
    {
        //搜索条件
        $consignee = I('consignee');
        $order_sn =  I('order_sn');
        $timegap = I('timegap');
        $order_status = I('order_status');
        $order_ids = I('order_ids');
        $order_prom_id =I('order_prom_id') ;
        $type = I('pay_code');
        $shipping_status = I('shipping_status');

        // I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;//订单状态
        // I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;//支付状态
        // I('pay_code') != '' ? $condition['type'] = I('pay_code') : false;//订单类型
        $where = array();//搜索条件
        if($consignee){
            $where['consignee'] = ['like','%'.$consignee.'%'];
        }
        if(I('pay_status')){
            $where['pay_status'] = I('pay_status');
        }
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        if($order_status){
            $where['order_status'] = $order_status;
        }
        if($order_prom_id){
            $where['mobile'] = $order_prom_id;
        }
        if($shipping_status){
            $where['shipping_status'] = $shipping_status;
        }
        if($type){
            $where['type'] = $type;
        }
        if(I('add_time_begin') && I('add_time_end')){
             $begin = I('add_time_begin');
            $end = I('add_time_end');
            $where['create_time'] = ['between',[$begin, $end]];
        }

        if($order_ids){
            $where['order_id'] = ['in', $order_ids];
        }
        $orderList = Db::name('self_mention_order')->where($where)->order('order_id')->select();
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
//        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单金额</td>';
//        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实际支付</td>';
//        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
//        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品信息</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单类型</td>';
        $strTable .= '</tr>';
        if(is_array($orderList)){
            $region	= get_region_list();
            foreach($orderList as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['self_mention_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d',strtotime($val['create_time'])) .' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
//                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].'</td>';
//                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_amount'].'</td>';
//                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
//                $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->pay_status[$val['pay_status']].'</td>';

//                    自提状态  1：待发货 2：待收货 3：已完成
                if($val['status'] == 1){
                    $strTable .= '<td style="text-align:left;font-size:12px;">待发货</td>';
                }elseif($val['status'] == 2){
                    $strTable .= '<td style="text-align:left;font-size:12px;">待收货</td>';
                }else{
                    $strTable .= '<td style="text-align:left;font-size:12px;">已完成</td>';
                }
                $orderGoods = D('order_goods')->where('order_id='.$val['order_id'])->select();
                $strGoods="";
                $goods_num = 0;
                foreach($orderGoods as $goods){
                    $goods_num = $goods_num + $goods['num'];
                    $strGoods .= "商品编号：".$goods['goods_sn']." 商品名称：".$goods['goods_name'];
                    if ($goods['spec_key_name'] != '') $strGoods .= " 规格：".$goods['spec_key_name'];
                    $strGoods .= "<br />";
                }
                // 0：批发  1：零售 2：自营
                unset($orderGoods);
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['num'].' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$strGoods.' </td>';
//                if ($val['type'] == 0 ) {
                    $strTable .= '<td style="text-align:left;font-size:12px;">代理</td>';
                    # code...
//                }else if($val['type'] == 1){
//                    $strTable .= '<td style="text-align:left;font-size:12px;">零售</td>';
//                }else{
//                    $strTable .= '<td style="text-align:left;font-size:12px;">自营 </td>';
//                }

                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'order');
        exit();
    }

    /**
     * 退货单列表
     */
    public function return_list(){
        return $this->fetch();
    }

    /*
     * ajax 退货订单列表
     */
    public function ajax_return_list(){
        // 搜索条件
        $order_sn =  trim(I('order_sn'));
        $order_by = I('order_by') ? I('order_by') : 'addtime';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status =  I('status');

        $where = " 1 = 1 ";
        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        empty($order_sn)&& !empty($status) && $where.= " and status = '$status' ";
        $count = M('return_goods')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr)){
            $goods_list = M('goods')->where("goods_id in (".implode(',', $goods_id_arr).")")->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('goods_list',$goods_list);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 添加一笔订单
     */
    public function add_order()
    {
        $order = array();
        //  获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //  获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //  获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //  获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();
        //  获取支付方式
        $payment_list = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        if(IS_POST)
        {
            $order['user_id'] = I('user_id');// 用户id 可以为空
            $order['consignee'] = I('consignee');// 收货人
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机
            $order['invoice_title'] = I('invoice_title');// 发票
            $order['admin_note'] = I('admin_note'); // 管理员备注
            $order['order_sn'] = date('YmdHis').mt_rand(1000,9999); // 订单编号;
            $order['admin_note'] = I('admin_note'); //
            $order['add_time'] = time(); //
            $order['shipping_code'] = I('shipping');// 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');
            $order['pay_code'] = I('payment');// 支付方式
            $order['pay_name'] = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');

            $goods_id_arr = I("goods_id/a");
            $orderLogic = new OrderLogic();
            $order_goods = $orderLogic->get_spec_goods($goods_id_arr);
            $result = calculate_price($order['user_id'],$order_goods,$order['shipping_code'],0,$order[province],$order[city],$order[district],0,0,0);
            if($result['status'] < 0)
            {
                 $this->error($result['msg']);
            }

           $order['goods_price']    = $result['result']['goods_price']; // 商品总价
           $order['shipping_price'] = $result['result']['shipping_price']; //物流费
           $order['order_amount']   = $result['result']['order_amount']; // 应付金额
           $order['total_amount']   = $result['result']['total_amount']; // 订单总价

            // 添加订单
            $order_id = M('order')->add($order);
            $order_insert_id = DB::getLastInsID();
            if($order_id)
            {
                foreach($order_goods as $key => $val)
                {
                    $val['order_id'] = $order_id;
                    $rec_id = M('order_goods')->add($val);
                    if(!$rec_id)
                        $this->error('添加失败');
                }

                M('order_action')->add([
                    'order_id'      => $order_id,
                    'action_user'   => session('admin_id'),
                    'order_status'  => 0,  //待支付
                    'shipping_status' => 0, //待确认
                    'action_note'   => $order['admin_note'],
                    'status_desc'   => '提交订单',
                    'log_time'      => time()
                ]);
                $this->success('添加商品成功',U("Admin/Order.Order/detail",array('order_id'=>$order_insert_id)));
                exit();
            }
            else{
                $this->error('添加失败');
            }
        }
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        return $this->fetch();
    }

    /**
     * 选择搜索商品
     */
    public function search_goods()
    {
    	$brandList =  M("brand")->select();
    	$categoryList =  M("goods_category")->select();
    	$this->assign('categoryList',$categoryList);
    	$this->assign('brandList',$brandList);
    	$where = ' is_on_sale = 1 and is_virtual =' . I('is_virtual/d',0);//搜索条件
    	I('intro')  && $where = "$where and ".I('intro')." = 1";
    	if(I('cat_id')){
    		$this->assign('cat_id',I('cat_id'));
            $grandson_ids = getCatGrandson(I('cat_id'));
            $where = " $where  and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件

    	}
        if(I('brand_id')){
            $this->assign('brand_id',I('brand_id'));
            $where = "$where and brand_id = ".I('brand_id');
        }
    	if(!empty($_REQUEST['keywords']))
    	{
    		$this->assign('keywords',I('keywords'));
    		$where = "$where and (goods_name like '%".I('keywords')."%' or keywords like '%".I('keywords')."%')" ;
    	}
        $goods_count =M('goods')->where($where)->count();
        $Page = new Page($goods_count,C('PAGESIZE'));
    	$goodsList = M('goods')->where($where)->order('goods_id DESC')->limit($Page->firstRow,$Page->listRows)->select();

        foreach($goodsList as $key => $val)
        {
            $spec_goods = M('spec_goods_price')->where("goods_id = {$val['goods_id']}")->select();
            $goodsList[$key]['spec_goods'] = $spec_goods;
        }
        if($goodsList){
            //计算商品数量
            foreach ($goodsList as $value){
                if($value['spec_goods']){
                    $count += count($value['spec_goods']);
                }else{
                    $count++;
                }
            }
            $this->assign('totalSize',$count);
        }

    	$this->assign('page',$Page->show());
    	$this->assign('goodsList',$goodsList);
    	return $this->fetch();
    }

    public function ajaxOrderNotice(){
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();
        echo $order_amount;
    }

    /**
     * 服务订单
     */
    public function to_shop(){
        return $this->fetch();
    }


    /*
     * ajax 服务订单列表
     */
    public function ajax_to_shop_list(){

        // 搜索条件
        $order_sn =  trim(I('order_sn'));
        $order_by = I('order_by') ? I('order_by') : 'add_time';
        $sort = I('sort') ? I('sort') : 'desc';
        $case_action =  I('case_action');
        $order_status =  I('order_status');
        $order_state = I('order_state');
        $order_type = I('order_type');
        $order_type_hid = I('order_type_hid');
        $pay_status = I('pay_status');

        // 获取当前门店id
        $suppliers_id = session('suppliers_id');
        $where = ' 1 + 1 ';

        // 当前登录 门店选择门店
        if (!empty($suppliers_id)) $where.= " and suppliers_id = ".$suppliers_id;

        // 判断当前进入的是哪个订单类型
        switch ($case_action) { 
            case 'send':
                $where .= " and order_type = 0 ";
                break;
            case 'home':
                $where .= " and order_type = 1 ";
                break;
            case 'door':
                $where .= " and order_type = 2 ";
                break;
        }

        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        !empty($order_status) && $where.= " and order_status = ".($order_status - 1 );
        !empty($order_state) && $where.= " and order_state = ".($order_state - 1);
        !empty($order_type) && $where.= " and order_type = ".($order_type - 1);
        !empty($pay_status) && $where.= " and pay_status = ".($pay_status - 1);
        $count = M('repair_order')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('repair_order')->where($where)->order("$order_by $sort")->limit("{$Page->firstRow},{$Page->listRows}")->select();
//        echo Db::name('repair_order')->getLastSql();exit;
        $orderLogic = new OrderLogic();
        $list = $orderLogic->getServiceInfo($list);
        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('order_type',$order_type);
        $this->assign('order_type_hid',$order_type_hid);
        $this->assign('list',$list);
        // dump($list);exit;
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }


    /**
     * 服务订单详情
     */
    public function to_shop_info(){
        //查询订单
        $order_id = I('id');
        $order_info = M('repair_order')->where("order_id= $order_id")->find();

        if(!$order_info)
        {
            $this->error('非法操作!');
            exit;
        }
        // 接收参数
        $order_buy_id = $order_info['order_buy_id'];
        $order_type = $order_info['order_type'];
        $order_status = $order_info['order_status'];
        $order_state = $order_info['order_state'];
        $pay_status = $order_info['pay_status'];
        if (!empty($order_info['order_buy_id'])) {
            // 商品id不为空 查询商品信息
            $field = 'goods_id,goods_name,spec_key_name,goods_num,goods_price';
            $goods = Db::name('order_goods')->field($field)->where(array('order_id'=> $order_buy_id))->select();
            foreach ($goods as $g_key => &$g_value) {
                $g_value['total_price'] = $g_value['goods_price'] * $g_value['goods_num'];
            }
            $list = array_column($goods,'total_price');
            $all_total_price = array_sum($list);
            $this->assign('goods',$goods);// 商品
            $this->assign('all_total_price',$all_total_price);// 商品总价格
        }
        // 上门   已付鉴定费  寄修 填写完单号 可分配工程师
        $condition_three = $order_info['order_type'] == 0 && !empty($order_info['shipping_code']) &&  empty($order_info['engineer_id']);
        $condition_two = $order_info['order_type'] == 1 && empty($order_info['engineer_id']) &&  $order_info['pay_status'] == 1;
        $condition_one = $order_info['order_type'] == 2 && $order_info['order_status'] == 1 && empty($order_info['engineer_id']);

        if (($condition_one) or ($condition_two) or ($condition_three)){
            // 工程师id为空  查询工程师信息提供选择
            $user_where['is_engineer'] = 1;
            $user_where['engineer_status'] = 1;
            $user_where['suppliers_id'] = session('suppliers_id');

            if (session('admin_id')){
                //  当前登录的是平台   查询当前门店所有工程师
                $user_where['suppliers_id'] = $order_info['suppliers_id'];
            }
            $field = 'user_id,sex,nickname';
            $engineer_info = Db::name('users')->field($field)->where($user_where)->select();
            $this->assign('engineer',$engineer_info);
        }

        if ($order_info['engineer_id']){
            $engineer = Db::name('users')->field('nickname,mobile')->where(['user_id' => $order_info['engineer_id']])->find();
            $this->assign('engineer_info',$engineer);
        }
        $door_field = 'suppliers_id,suppliers_name,suppliers_desc,suppliers_phone,province_id,city_id,district_id,address';
        $door_info = Db::name('suppliers')
                        ->field($door_field)
                        ->where(array('suppliers_id'=>$order_info['suppliers_id'], 'is_check' => 1))
                        ->find();
        $door_info['province_id'] = get_region($door_info['province_id'])['name'];
        $door_info['city_id'] = get_region($door_info['city_id'])['name'];
        $door_info['district_id'] = get_region($door_info['district_id'])['name'];
        // 对字段值进行转换
        $orderLogic = new OrderLogic();
        $order_arr[]= $order_info;
        $order_info = $orderLogic->getServiceInfo($order_arr);
        $action_log = M('repair_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();

        $su = session('suppliers_id');
        if ($su)$this->assign('su',$su);

        $this->assign('door_info',$door_info);
        $this->assign('order_buy_id',$order_buy_id);
        $this->assign('action_log',$action_log); //  订单操作日志
        $this->assign('order_type',$order_type);//  订单类型  0 寄修 1 上门 2 到店
        $this->assign('order',$order_info[0]); 
        $this->assign('pay_status',$pay_status); 
        $this->assign('order_status',$order_status); 
        $this->assign('order_state',$order_state); 
        return $this->fetch();
    }

    /**
     * 寄修 平台填写单号 物流信息
     */
    public function update_server_order_info(){
        $d = I('order_id/d');
        if (empty($d)) $this->error('请选择订单');
        $order_id = I('order_id/d');
        $action = input('action');
        if ($action == 5) {
            $order_status = input('post.order_status');
            switch ($order_status){
                case 1;
                    $order_status = '维修中';
                    break;
                case 2;
                    $order_status = '已完成';
                    break;
                case 5;
                    $order_status = '已取消';
                    break;
            }
        }
        $operator = session('suppliers_id')?'门店':'平台';
        $result = Db::name('repair_order')->where(['order_id' => $order_id])->find();
        if (!$result) $this->error('暂无该数据');
        $data = input('post.');
        unset($data['order_id']);
        switch ($action) {
            case 1:
                $action_content = $operator.'修改用户实付价格'.input('post.paid_price').'元、订单总价'.input('post.total_price').'元、价格调整幅度'.input('post.discount').'元';
                break;
            case 2:
                $action_content = $operator.'已修完,添加物流订单信息'.input('post.platform_code');
                break;
            case 3:
                $action_content = $operator. '确认用户已收物流快件';
                $data['confirm_time'] = time();
                break;
            case 4:
                $action_content = $operator.'分配工程师,工程师编号'.input('post.engineer_id');
                $data['order_status'] = 1;
                break;
            case 5:
                $order_status = input('post.order_status');
                if($order_status == 2){
                    $data['order_status'] = 2;
                    $data['pay_status'] = 1;
                    $data['last_time'] = time();
                    $suppliers_id = input('post.engineer_id');
                    $action_content = $operator.'修改订单状态为:订单已完成';
                    if ($suppliers_id){
                        $action_content .= ' 门店ID为'.$suppliers_id;
                    }
                }elseif ($order_status == 1){
                    $data['order_status'] = 1;
                    $data['last_time'] = time();
                    $action_content = $operator.'修改订单状态为:订单维修中';

                }elseif ($order_status == 5){
                    $data['order_status'] = 5;
                    $data['last_time'] = time();
                    $action_content = $operator.'修改订单状态为:取消订单';
                }
                break;
            case 6:
                $action_content = $operator.'取消该订单,添加物流订单信息'.input('post.platform_code');
                break;
                break;
            default:
                $this->error('异常');
                break;
        }
        $res = Db::name('repair_order')->where(['order_id' => $order_id])->save($data);
        logServerOrder($order_id, $operator.'后台服务订单修改',$action_content);
        $mes = empty($res) ?   '提交失败': '提交成功';
        $this->success($mes);
    }

    /**
     *  寄修订单列表
     * */
    public function to_shop_send(){
        return $this->fetch();
    }

    /**
     *  到店订单列表
     * */
    public function to_shop_door(){
        return $this->fetch();
    }

    /**
     *  上门订单列表
     * */
    public function to_shop_home(){
        return $this->fetch();
    }

    /*
     * 确认  取消 到店订单
     * */
    public function confirm_service_order(){
        $id = input('get.id');
        $action = input('get.action');
        if (!$id) $this->error('无订单信息');
        $order = Db::name('repair_order')->where(['order_id'=>$id])->find();
        if (!$order)$this->error('无订单信息');
        $operator = session('suppliers_id')?'门店':'平台';
        switch ($action){
            case 'cancel':// 取消订单
                $data['order_status'] = 5;
                $data['last_time'] = time();
                $note = $operator.'修改订单状态为:取消订单';
                break;
            case 'confirm':// 确认订单
                $data['order_status'] = 1;
                $data['last_time'] = time();
                $note = $operator.'修改订单状态为:订单接收';
                break;
            case 'finish': // 完成订单
                $data['order_status'] = 2;
                $data['pay_status'] = 1;
                $data['last_time'] = time();
                $note = $operator.'修改订单状态为:订单已完成';
                break;
            default:
                $this->error('页面异常');
                break;
        }
        $res = Db::name('repair_order')->where(['order_id' => $id])->save($data);
        logServerOrder($id,$operator.'后台服务订单修改',$note);
        $mes = empty($res) ?   '提交失败': '提交成功';
        $this->success($mes);

    }

    /*
     * 支付订单
     * */
    public function pay_order(){
        $id = input('id');
        $order =  Db::name('repair_order')->where(['order_id' => $id ])->find();
        // 工程师鉴定过才可走支付
        if (empty($order['engineer_judge'])) exit(40001);
        $updata = array('pay_status' => 1, 'pay_time' => time());
        $res = M('repair_order')->where(['order_id' => $id ])->save($updata);
        if ($res) {
            logServerOrder($order['order_id'], $order['order_sn'].'订单付款'.$order['paid_price'].'元成功', '付款成功', $order['user_id']);
            $wechat = new \app\common\logic\WechatLogic;
            $wechat->sendTemplateMsgServiceOrderPay($order);
            //用户支付, 发送短信给商家  //下单
            $res = checkEnableSendSms("4");
            if (!$res || $res['status'] != 1) return;
            $sender = tpCache("sms.order_pay_sms_enable");
            if (empty($sender)) return;
            // 查出当前门店的联系方式
            $sender = Db::name('suppliers')->where(['suppliers_id' => $order['suppliers_id']])->value('suppliers_phone');
            if (empty($sender)) return;
            $params = array('status' => '订单待处理','remark'=>'请留意订单详细信息');
            sendSms("8", $sender, $params);
            $this->success('支付完成','/index.php/Admin/Order.Order/to_shop_info/id/'.$id);
        } else {
            $this->error('操作失败');

        }
    }

    /*
     *
     * 更新单条服务订单信息  测试用
     * */
    public function update_order(){
        return $this->fetch();
    }

    /*
    * ajax 服务订单列表测试用
    */
    public function ajax_update_order_list(){

        // 搜索条件
        $order_sn =  trim(I('order_sn'));
        $order_by = I('order_by') ? I('order_by') : 'add_time';
        $sort = I('sort') ? I('sort') : 'desc';
        $case_action =  I('case_action');
        $order_status =  I('order_status');
        $order_state = I('order_state');
        $order_type = I('order_type');
        $order_type_hid = I('order_type_hid');
        $pay_status = I('pay_status');

        // 获取当前门店id
        $suppliers_id = session('suppliers_id');
        $where = ' 1 + 1 ';

        // 当前登录 门店选择门店
        if (!empty($suppliers_id)) $where.= " and suppliers_id = ".$suppliers_id;

        // 判断当前进入的是哪个订单类型
        switch ($case_action) {
            case 'send':
                $where .= " and order_type = 0 ";
                break;
            case 'home':
                $where .= " and order_type = 1 ";
                break;
            case 'door':
                $where .= " and order_type = 2 ";
                break;
        }

        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        !empty($order_status) && $where.= " and order_status = ".($order_status - 1 );
        !empty($order_state) && $where.= " and order_state = ".($order_state - 1);
        !empty($order_type) && $where.= " and order_type = ".($order_type - 1);
        !empty($pay_status) && $where.= " and pay_status = ".($pay_status - 1);
        $count = M('repair_order')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('repair_order')->where($where)->order("$order_by $sort")->limit("{$Page->firstRow},{$Page->listRows}")->select();
//        echo Db::name('repair_order')->getLastSql();exit;
        $orderLogic = new OrderLogic();
        $list = $orderLogic->getServiceInfo($list);
        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('order_type',$order_type);
        $this->assign('order_type_hid',$order_type_hid);
        $this->assign('list',$list);
        // dump($list);exit;
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    /*
     * 测试调试单条订单状态
     * */
    public function order_info(){
        //查询订单
        $order_id = I('id');
        $order_info = M('repair_order')->where("order_id= $order_id")->select();
        if(!$order_info)
        {
            $this->error('非法操作!');
            exit;
        }        // 接收参数
        $order_type = $order_info[0]['order_type'];
        $order_status = $order_info[0]['order_status'];
        $order_state = $order_info[0]['order_state'];
        $pay_status = $order_info[0]['pay_status'];

        // 对字段值进行转换
        $orderLogic = new OrderLogic();
        $order_info = $orderLogic->getServiceInfo($order_info);

        $this->assign('pay_status',$pay_status);
        $this->assign('order_state',$order_state);
        $this->assign('order_status',$order_status);
        $this->assign('order_type',$order_type);
        $this->assign('order',$order_info[0]);
        return $this->fetch();

    }

    /*
     * 更新单条订单信息  后面可修改
     * */
    public function  update_order_info(){
        $data = input('post.');
        $res = Db::name('repair_order')->where(['order_id' => $data['order_id']])->save(data);
        if ($res) {
            $this->error('提交成功');
        } else {
            $this->error('失败');
        }
    }


    /**
     * 代理提货单
     */
    public function agent_order()
    {
        return $this->fetch();
    }

    public function agentindex()
    {
        $timegap = I('timegap');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        }else{
            //@new 新后台UI参数
            $begin = I('add_time_begin');
            $end = I('add_time_end');
        }

        // 搜索条件
        $condition = array();
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $consignee =  ($keyType && $keyType == 'consignee') ? $keywords : I('consignee','','trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;//收货人

        if($begin && $end){
            $condition['create_time'] = array('between',"$begin,$end");
//            $where_sum['add_time'] = array('between',"$begin,$end");
        }
//        $condition['order_prom_type'] = array('lt',5);

        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;//订单编号
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;//订单

        $order_prom_id = ($keyType && $keyType == 'order_prom_id') ? $keywords : I('order_prom_id') ;
        $order_prom_id ? $condition['mobile'] = trim($order_prom_id) : false;//手机号

        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;//订单状态
        I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;//支付状态
        I('pay_code') != '' ? $condition['pay_code'] = I('pay_code') : false;//支付方式
        I('shipping_status') != '' ? $condition['status'] = I('shipping_status') : false;//发货状态
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('self_mention_order')->where($condition)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
//        $total_sales = Db::name('self_mention_order')->where(['order_status'=>['in',[2,4]]])->where($where_sum)->sum('order_amount');
//        $this->assign('total_sales',$total_sales);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        $data = Db::name('self_mention_order')->where($condition)->limit($Page->firstRow,$Page->listRows)->select();
        foreach ($data as $key => $val){
            if($val['type'] != 2){
                $goods = Db::name('order_goods')->where("goods_id = {$val['goods_id']}")->find();
            }
            $data[$key]['goods_name'] = $goods['goods_name'];
        }
//        dump($condition);
//        dump($data);exit;
        $this->assign('orderList',$data);
//        dump($data);exit;
        return $this->fetch();
    }

    /**
     * 代理提货单
     */
    public function income_record(){
        $model = M('balancelog');
        $map = array();
        $map['type'] = array('in','11,17');//寄售收入

        $goods_name = I('goods_name');
        if($goods_name){
            $map['goods_name'] = array('like',"%$goods_name%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        $count = $model->where($map)->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $stock_list = $model->alias('b')->join('users u','b.userId=u.user_id')->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('stock_list',$stock_list);
        return $this->fetch();
    }

    /**
     * @return mixed 平台支出记录
     */
    public function expense_log(){
        $map = array();
        $add_time_begin = I('add_time_begin');
        $keytype = I('keytype');
        $keyword = I('keyword');

        if($add_time_begin){
            $begin = strtotime($add_time_begin);
            $end = strtotime("+1 month",$begin);
            $begin = date("Y-m-d H:i:s",$begin);
            $end = date("Y-m-d H:i:s",$end);
            $map['createTime'] = array('between',"$begin,$end");
        }

        if($keyword){
            if($keytype){
                $map[$keytype]=array('like','%'.$keyword.'%');
            }else{
                $map['account|mobile']=array('like','%'.$keyword.'%');
            }
        }
        $map['a.type']=array('in',[8,15]);

        $count =  M('balancelog')->alias('a')
            ->join('users u','a.userId=u.user_id','left')
            ->group('a.userId')
            ->where($map)->count();
        $page = new Page($count);


        $lists  = M('balancelog')->alias('a')
            ->join('users u','a.userId=u.user_id','left')
            ->where($map)->limit($page->firstRow.','.$page->listRows)
            ->field('a.id,sum(num) as total_num,u.user_id,u.mobile')
            ->group('a.userId')->select();

        $this->assign('page',$page->show());
        $this->assign('total_count',$count);
        $this->assign('add_time_begin',$add_time_begin);
        $this->assign('list',$lists);
        return $this->fetch();
    }
    public function expense_logs(){
        $map = array();
        $user_id = input('user_id');
        $add_time_begin = I('add_time_begin');
        $keytype = I('keytype');
        $keyword = I('keyword');

        if($add_time_begin){
            $begin = strtotime($add_time_begin);
            $end = strtotime("+1 month",$begin);
            $begin = date("Y-m-d H:i:s",$begin);
            $end = date("Y-m-d H:i:s",$end);
            $map['createTime'] = array('between',"$begin,$end");
        }

        if($keyword){
            if($keytype){
                $map[$keytype]=array('like','%'.$keyword.'%');
            }else{
                $map['account|mobile']=array('like','%'.$keyword.'%');
            }
        }
        $map['a.userId']=$user_id;
        $map['a.type']=array('in',[8,15]);

        $count =  M('balancelog')->alias('a')
            ->join('users u','a.userId=u.user_id','left')
            ->where($map)->count();
        $page = new Page($count);


        $lists  = M('balancelog')->alias('a')
            ->join('users u','a.userId=u.user_id','left')
            ->where($map)->limit($page->firstRow.','.$page->listRows)
            ->field('a.*,u.user_id,u.mobile')
            ->select();

        $this->assign('page',$page->show());
        $this->assign('total_count',$count);
        $this->assign('add_time_begin',$add_time_begin);
        $this->assign('list',$lists);
        return $this->fetch();
    }
}
