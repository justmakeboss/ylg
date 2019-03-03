<?php

namespace app\mobile\controller;

use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;
use think\AjaxPage;
use app\common\logic\ActivityLogic;

class Hot extends MobileBase
{
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 热卖产品
     * 代理产品
     */
    public function goods_list()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'is_hot' => 1,
            'type_id' => 0, //代理零售商品
//            'UNIX_TIMESTAMP(g.agentstart_time)'=>array('<=','UNIX_TIMESTAMP(NOW())')  //代理开始时间
        );
//        dump(Db::name('goods')->where('UNIX_TIMESTAMP(agentstart_time) <= UNIX_TIMESTAMP(NOW())')->select());exit;
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        //批发自转零售
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
//        dump($count);exit;
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
//        $list = Db::name('goods')
//            ->where($where)
////            ->page($p, $pagesize)
//            ->select();

//        print_r(Db::table('contract')->getLastSql());exit;


//        dump($list);exit;
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        if (I('is_ajax')) {
            return $this->fetch('ajax_goods_list');//输出分页
        }
        return $this->fetch();
    }
}