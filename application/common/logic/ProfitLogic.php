<?php

namespace app\common\logic;

use think\Model;
use think\Db;

/**
 * 收益逻辑
 */
class ProfitLogic extends model
{

//     public $rebate = 0; //所有下级返点
     public $achievement = 0; //直推消费商以及所有下级业绩
    /**
    * 收益总和
    */
    public function profit()
    {
        //配置信息
        $system = tpCache('ylg_spstem_role');
//       $users = Db::name('users')->where('level > 0')->select();
        $users = Db::name('users')->where('level > 0')->order("user_id desc")->select();
       foreach($users as $k=>$v){
            if($v['level'] == 1){
                $this->consumerprofit($v,$system);
//                dump($this->consumerprofit($v,$system));
            }elseif ($v['level'] == 2){
                $this->agentprofit($v,$system);
            }elseif($v['level'] == 3){
                $this->partnerprofit($v,$system);
            }
       }
      $this->zero_clearing();
    }

    /**
     * 消费商收益
     */
    public function consumerprofit($user,$system)
    {

        //获取返点配置

        //所有直推下级
       $users = Db::name('users')->where('first_leader',$user['user_id'])->field('user_id,monthly_performance')->select();
        if(empty($users)){
            return true;
        }else{
            $price = 0;
            for ($i=0;$i<count($users);$i++){
                    $money = $users[$i]['monthly_performance'];
//                    dump($users[$i]);
//                    dump($money);exit;
                    $price +=$money;
//                    dump($price);
                    //自身返点
                    // Db::name('users')->where('user_id',$users[$i]['user_id'])->inc('rebate_revenue',$money)->update();
            }
            if($price >= $system['consumer']){
                $money = $price*$system['consumer2']*$system['personal_income'];
                    $price = $price*$system['consumer2']-$money;
                if(Db::name('users')->where('user_id',$user['user_id'])->inc('user_money',$price)->update()){
                    
                    //余额日志
                    balancelog($user['user_id'],$user['user_id'],$price,4,$user['user_money'],$user['user_money']+$price);
                    balancelog($user['user_id'],$user['user_id'],-$money,17,$user['user_money'],$user['user_money']+$price);

                }

            }
            
        }
        return true;
    }

    /**
     * 返代理商无限层下级业绩补贴
     */
    public function agentprofit($user,$system)
    {
        $this->saleprofit($user,$system);

        // 判断所有下级业绩是否大于0
        if($user['performance'] > 0){

            $this->subordinate($user,$system,$user['user_id']);
        }
        return true;

    }
    /**
     * 合伙人收益
     */
    public function partnerprofit($user,$system)
    {
        $this->saleprofit($user,$system);
        //直推代理商收入
        $this->agent($user['level'],$user,$system,$user['user_id']);
        //直推消费商以及所有下级业绩补贴
        $this->consumer($user['user_id'],$user,$system);

//        $money = $user['rebate_revenue']*$system['agent_partner'];
//        //所有直推消费商
//        $users = Db::name('users')->where("first_leader={$user['user_id']} AND level = 1")->select();
//        if(empty($users) && $money > 0){
//            return true;
//        }elseif(empty($users)){
//            if(Db::name('users')->where('user_id',$user['user_id'])->setInc('user_money',$money)){
//                //余额日志
//                balancelog($user['user_id'],$user['user_id'],$money,6,$user['user_money'],$user['user_money']+$money);
//            }
//        }else{
//            for ($i=0;$i<count($users);$i++){
//
//                    $this->achievement = $this->achievement+($users[$i]['performance']+$users[$i]['pay_points'])*$system['agent_partner2'];
//
//            }
//            if(Db::name('users')->where('user_id',$user['user_id'])->setInc('user_money',$money+$this->achievement)){
//                //余额日志
//                balancelog($user['user_id'],$user['user_id'],$money+$this->achievement,6,$user['user_money'],$user['user_money']+$money+$this->achievement);
//            }
//        }
    }

    /**
     * 销售额收益
     */
    public function saleprofit($user,$system)
    {
        //获取销售额配置信息
       $arr  = unserialize($system['pushs']);
       $this->achievement = 0;
        $this->sum($user,$user['level']);
       if($this->achievement <= 0){
            return true;
       }
       $salenum  = $user['monthly_performance']+$this->achievement;

        //判断
        for($i = count($arr)-1;$i>=0;$i--){
            if($salenum >= $arr[$i]['sales']){
                $money = $salenum*$arr[$i]['rebate']*$system['personal_income'];
                $price = $salenum*$arr[$i]['rebate']-$money;
                break;
            }
        }
        if(empty($money)){
            return true;
        }else{
            if(Db::name('users')->where('user_id',$user['user_id'])->setInc('user_money',$price)){
                //余额日志
                balancelog($user['user_id'],$user['user_id'],$price,7,$user['user_money'],$user['user_money']+$price);
                balancelog($user['user_id'],$user['user_id'],-$money,17,$user['user_money'],$user['user_money']+$price);

            }
        }
        //生产日志记录
    }

    public function sum($user,$level,$sumid='')
    {
        $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level < $level AND monthly_performance > 0")->column('user_id');

        if(empty($users) && empty($sumid)){
            return false;
        }elseif(empty($users)){
            $num = Db::name('users')->where("user_id in($sumid)")->sum('monthly_performance');
            $this->achievement = $num;
        }else{
            $user['user_id'] = implode(',',$users);
            $sumid = trim($sumid.','.$user['user_id'],',');
            $this->sum($user,$level,$sumid);
        }
    }
    /**
     * 下级业绩补贴
     */
    public function subordinate($user,$system,$id,$money = 0)
    {
        //所有直推下级消费商
        $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level = 1")->select();
        if(empty($users) && $money == 0){
            return true;
        }elseif(empty($users)){
            $user = Db::name('users')->where('user_id',$id)->find();
            $price = $money*$system['agent_rebate'];
            $money = $money*$system['agent_rebate']*$system['personal_income'];
            $price = $price-$money;
            //增加收益积分和返点收入
            if(Db::name('users')->where('user_id',$user['user_id'])->inc('user_money',$price)->inc('rebate_revenue',$price)->update()){
                //余额日志
                balancelog($user['user_id'],$user['user_id'],$price,5,$user['user_money'],$user['user_money']+$price);
                balancelog($user['user_id'],$user['user_id'],-$money,17,$user['user_money'],$user['user_money']+$price);
            }
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // 当月业绩*补贴 = 返点
                $money += $users[$i]['monthly_performance'];
                $strid .=",".$users[$i]['user_id'];

            }
            $user['user_id'] = trim($strid,",");
            $this->subordinate($user,$system,$id,$money);
        }
    }
    /**
     * 获取直推代理商收入
     */
    public function agent($level,$user,$system,$id,$money = 0)
    {
        //获取下级返点收入大于0 的用户
        $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level < $level AND rebate_revenue > 0")->select();
        // dump($users);
        if(empty($users) && empty($money)){
            return true;
        }elseif(empty($users) && $money > 0){

//             直推代理商收入
            // dump($money);
             $price = $money*$system['agent_partner'];
               $money  = $money*$system['agent_partner']*$system['personal_income'];
               $price  = $price-$money;
               // dump($money);
               $user= Db::name('users')->where('user_id',$id)->find();
                if(Db::name('users')->where('user_id',$id)->inc('user_money',$price)->update()){
                    //余额日志
                    balancelog($id,$id,$price,6,$user['user_money'],$user['user_money']+$price);
                    balancelog($user['user_id'],$user['user_id'],-$money,17,$user['user_money'],$user['user_money']+$price);

                }
                return true;
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // dump($money);
                // dump($users[$i]['rebate_revenue']);
                // 累计返点收入
                $money += $users[$i]['rebate_revenue'];
                $strid.=",".$users[$i]['user_id'];
            }
            $user['user_id'] = trim($strid,',');
            $this->agent($level,$user,$system,$id,$money);
        }
    }

    /**
     * 返消费商以及所有下级的业绩补贴
     */
    public function consumer($id,$user,$system,$num = 1,$money=0)
    {
        // dump($user);
        //所有直推下级消费商
        if($num = 1){
            $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level = 1 AND monthly_performance > 0")->select();
        }else{
            $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND monthly_performance > 0")->select();
        }

        if(empty($users) && $money==0){
            return true;
        }elseif(empty($users)){
            $user= Db::name('users')->where('user_id',$id)->find();
            $price = $money*$system['agent_partner2'];
             $money = $money*$system['agent_partner2']*$system['personal_income'];
             $price = $price-$money;
            //增加收益积分和返点收入
            if(Db::name('users')->where('user_id',$user['user_id'])->inc('user_money',$price)->inc('rebate_revenue',$price)->update()){
                //余额日志
                balancelog($user['user_id'],$user['user_id'],$price,12,$user['user_money'],$user['user_money']+$price);
                balancelog($user['user_id'],$user['user_id'],-$money,17,$user['user_money'],$user['user_money']+$price);
            }
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // 当月业绩*补贴 = 返点
                $money += $users[$i]['monthly_performance'];

                $strid.=",".$users[$i]['user_id'];
            }
            $user['user_id'] = trim($strid,',');
//            dump($user['user_id']);
            $this->consumer($id,$user,$system,$num+1,$money);
        }
    }

    //清零
    public function zero_clearing()
    {
        //当月业绩清0；赋值上月业绩
        Db::query("UPDATE tp_users  SET last_monthly_performance = monthly_performance ,monthly_performance=0");

        $arr = array(
            'rebate_revenue'=>0,
            'total_jackpot'=>0,
//            'monthly_performance'=>0,
            'performance'=>0
        );
        Db::name('users')->where("level > 0")->update($arr);
    }

    //时间到  商品自动自提  出售列表自动返回代理余额
    public function end_time()
    {
        $mealid = Db::name('goods')->where("type_id = 6 AND is_on_sale = 1 AND status = 0 AND UNIX_TIMESTAMP(agentend_time) < UNIX_TIMESTAMP(NOW())")->column('goods_id');
        if(!empty($mealid)){
            $mealid = implode(',',$mealid);
            //        $goodslist = Db::name('order')->alias('o')
            //            ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
            //            ->group('o.user_id')->select();

            $meallist = Db::name('order')->alias('o')
                ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$mealid})")->field('g.goods_id,g.order_id,g.quota,g.goods_price,user_id,setmeal_id,sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                ->group('o.user_id,g.setmeal_id')->select();

            foreach($meallist as $k => $v){
                //商品总数量 - 出售数量 - 提货数量 = 剩余数量  自动提货
                $num = $v['nums']-$v['sel']-$v['self'];
                if($num>0){
                    if(!$this->agent_order($v,$num)){
                        echo '提货有误';
                    }
                }
            }
            $result = Db::name('goods')->where("status = 0 AND type_id = 6 AND UNIX_TIMESTAMP(agentend_time) <= UNIX_TIMESTAMP(NOW())")->update(['status'=>1]);
            if($result){
                $data = Db::name('goods_consignment')->field('goods_id,sum(num) snum')->where("goods_id in ($mealid)")->group('goods_id')->select();
                foreach ($data as $key => $value) {
                    Db::name('goods')->where("goods_id = {$value['goods_id']}")->update(['store_count'=>$value['snum']]);
                }
            }
        }

        $goods = Db::name('goods')->where("type_id = 6 AND is_on_sale = 1 AND status =1 AND UNIX_TIMESTAMP(saleend_time) < UNIX_TIMESTAMP(NOW())")->column('goods_id');
        if(!empty($goods)){
            $goods_id = implode(',',$goods);
        //        $goodslist = Db::name('order')->alias('o')
        //            ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
        //            ->group('o.user_id')->select();

            $goodslist = Db::name('order')->alias('o')
                ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->group('o.user_id,g.setmeal_id')->select();

            foreach($goodslist as $k => $v){
            //商品总数量 - 出售数量 - 提货数量 = 剩余数量  自动提货
//                $num = $v['nums']-$v['sel']-$v['self'];
//                if($num>0){
//                    if(!$this->agent_order($v,$num)){
//                        echo '提货有误';
//                    }
//                }
                if(!$this->recovery($v)){
                    echo '回收有误';
                }
             }
            Db::name('goods')->where("goods_id in($goods_id)")->update(['is_on_sale'=>0]);
        }

    }

    //提货订单生成
    public function agent_order($data,$num)
    {
        if($num <= 0){
            return true;
        }
        $address = Db::name('user_address')->where("user_id = {$data['user_id']} AND is_default = 1")->find();
        $orderLogic = new OrderLogic();
        $arr = array(
            'user_id' => $data['user_id'],
            'self_mention_sn' => $orderLogic->get_order_sn(),
            'setmeal_id' => $data['setmeal_id'],
            'goods_id' => $data['goods_id'],
            'order_id' => $data['order_id'],
            'num' => $num,
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
//        Db::startTrans();
        if (Db::name('self_mention_order')->insert($arr)) {
            if (Db::name('order_goods')->where("order_id = {$data['order_id']}")->setInc('self_mention', $num)) {
                return true;
//                Db::commit();
            }else{
                return false;
//                Db::rollback();
            }
        }else{
            return false;
//            Db::rollback();
        }
    }

    //回收没有出售完的商品
    public function recovery($data)
    {
        $list = Db::name('goods_consignment')->where("user_id = {$data['user_id']} AND setmeal_id = {$data['setmeal_id']}")->field(' sum(surplus_num) num')->find();
        if(!empty($list) && $list['num'] >0){
            Db::startTrans();
            $user = Db::name('users')->where("user_id = {$data['user_id']}")->find();
            $result = Db::name('users')->where("user_id = {$data['user_id']}")->inc('user_money',$data['goods_price']*$list['num'])->inc('frozen_money',$data['quota']*$list['num'])->update();
            $results = Db::name('goods_consignment')->where("user_id = {$data['user_id']} AND setmeal_id = {$data['setmeal_id']}")->update(['surplus_num'=>0]);
            if($result && $results){
                balancelog($data['user_id'],$data['user_id'],$data['goods_price']*$list['num'],14,$user['user_money'],$user['user_money']+$data['goods_price']*$list['num']);
                integrallog($data['user_id'],$data['user_id'],$data['quota']*$list['num'],10,$user['frozen_money'],$user['frozen_money']+$data['quota']*$list['num']);
                Db::commit();
                return true;
            }else{
                Db::rollback();
                return false;
            }
        }
        return true;
    }
}