<?php

namespace app\home\controller;
use think\Page;
use think\Verify;
use think\Image;
use think\Db;
class Notification {

    public function index()
    {
        $alipay_data = I('post.');

        $data = array();

        $data['order_sn'] = $alipay_data['out_trade_no'];
        $data['trade_no'] = $alipay_data['trade_no'];

        $lists = M('recharge')
            ->alias('r')
            ->join('tp_users u','u.user_id=r.user_id','right')
            ->where('r.order_sn='.$data['order_sn'])
            ->where('r.pay_status=0')
            ->field('u.user_id,u.user_money,r.account,r.order_id')
            ->find();
        if($lists){



            if ($alipay_data['total_amount']!=$lists['account'])
            {
                echo "fail";die;
            }

//            print_r($data);die;
            $update_data['order_id']=$lists['order_id'];
            $update_data['pay_status'] = 1;
            $update_data['pay_time']=time();

            $res = M('recharge')->update($update_data);
            if ($res) {
                    M('users')->where('user_id='.$lists['user_id'])->setInc('user_money',$lists['account']);
                    balancelog($lists['order_id'],$lists['user_id'],$lists['account'],1,$lists['user_money'],$lists['user_money']+$lists['account']);

                echo "success";die;
            }else{
                echo "fail";die;
            }
        }else{
            echo "fail";die;
        }

    }


}