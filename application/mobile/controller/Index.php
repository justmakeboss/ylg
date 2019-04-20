<?php

namespace app\mobile\controller;

use app\common\logic\JssdkLogic;
use Think\Db;
use think\Page;
use app\common\logic\CartLogic;

class Index extends MobileBase
{

    public function rankingListOfWeek()
    {
        $page = (int)I('p') ? (int)I('p') : 0;
        $rowsOfPerPage = 15;
        $usersLevelGT1 = Db::name('users')->where('level', '>', 0)->field('user_id,mobile,nickname,head_pic')->select();
        $d = [];
        foreach ($usersLevelGT1 as $item) {
            $count = Db::name('users')->where('first_leader', $item['user_id'])->where('reg_time', '>=', strtotime(getTheBeginOfTheWeek()))->where('reg_time', '<', strtotime(getTheEndOfTheWeek()))->where('level', '>=', 2)->count();
            $d[] = ['user_id' => $item['user_id'], 'count' => $count];
        }
        //冒泡排序
        for ($i = 0; $i < count($d) - 1; $i++) {
            for ($j = 0; $j < count($d) - 1 - $i; $j++) {

                if ($d[$j]['count'] < $d[$j + 1]['count']) {
                    $temp = $d[$j];
                    $d[$j] = $d[$j + 1];
                    $d[$j + 1] = $temp;
                }
            }
        }
        $users = [];
        $d = array_slice($d, 0, 10);
        foreach ($d as $rank => $v) {
            $currentUserInfo = current(array_filter($usersLevelGT1, function ($user) use ($v) {
                return $user['user_id'] == $v['user_id'];
            }));
            $currentUserInfo['count'] = $v['count'];
            $currentUserInfo['rank'] = ++$rank;
            $currentUserInfo['zhitui'] = Db::name('balancelog')->where(['userId' => $v['user_id'], 'type' => 6, 'createTime' => ['>=', strtotime(getTheBeginOfTheWeek())], 'createTime' => ['<', strtotime(getTheEndOfTheWeek())]])->sum('num') ?: 0;
            $currentUserInfo['jishou'] = Db::name('balancelog')->where(['userId' => $v['user_id'], 'type' => 11, 'createTime' => ['>=', strtotime(getTheBeginOfTheWeek())], 'createTime' => ['<', strtotime(getTheEndOfTheWeek())]])->sum('num') ?: 0;
            array_push($users, $currentUserInfo);
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    public function rankingListOfMonth()
    {
        $page = (int)I('p') ? (int)I('p') : 0;
        $rowsOfPerPage = 15;
        $usersLevelGT1 = Db::name('users')->where('level', '>', 0)->field('user_id,mobile,nickname,head_pic')->select();
        $d = [];
        foreach ($usersLevelGT1 as $item) {
            $count = Db::name('users')->where('first_leader', $item['user_id'])->where('reg_time', '>=', strtotime(getTheBeginOfTheMonth()))->where('reg_time', '<', strtotime(getTheEndOfTheMonth()))->where('level', '>=', 2)->count();
            $d[] = ['user_id' => $item['user_id'], 'count' => $count];
        }
        //冒泡排序
        for ($i = 0; $i < count($d) - 1; $i++) {
            for ($j = 0; $j < count($d) - 1 - $i; $j++) {

                if ($d[$j]['count'] < $d[$j + 1]['count']) {
                    $temp = $d[$j];
                    $d[$j] = $d[$j + 1];
                    $d[$j + 1] = $temp;
                }
            }
        }
        $d = array_slice($d, 0, 10);
        $users = [];
        foreach ($d as $rank => $v) {
            $currentUserInfo = current(array_filter($usersLevelGT1, function ($user) use ($v) {
                return $user['user_id'] == $v['user_id'];
            }));
            $currentUserInfo['count'] = $v['count'];
            $currentUserInfo['rank'] = ++$rank;
            $currentUserInfo['zhitui'] = Db::name('balancelog')->where(['userId' => $v['user_id'], 'type' => 6, 'createTime' => ['>=', strtotime(getTheBeginOfTheMonth())], 'createTime' => ['<', strtotime(getTheEndOfTheMonth())]])->sum('num') ?: 0;
            $currentUserInfo['jishou'] = Db::name('balancelog')->where(['userId' => $v['user_id'], 'type' => 11, 'createTime' => ['>=', strtotime(getTheBeginOfTheMonth())], 'createTime' => ['<', strtotime(getTheEndOfTheMonth())]])->sum('num') ?: 0;
            array_push($users, $currentUserInfo);
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    public function getWrong()
    {
        header('content-type:text/html;charset=utf-8');
        $goodsConsignUsers = Db::name('goods_consignments')->group('user_id')->field('user_id')->select();
        $goodsConsignUserIds = [];

        foreach ($goodsConsignUsers as $goodsConsignUser) {
            if(!in_array($goodsConsignUser['user_id'], $goodsConsignUserIds)) {
                $goodsConsignUserIds[] = $goodsConsignUser['user_id'];
            }
        }
        $users = Db::name('users')->whereIn('user_id', $goodsConsignUserIds)->field('user_id,user_money,mobile')->select();
//        $users = Db::name('users')->field('user_id,user_money,mobile')->select();
        $wrongUsers = [];
        $rightUsers = [];
        $lostTotal = 0;
        $lostTotal2 = 0;
        foreach ($users as $user) {


            $sumBalanceLog = Db::name('balancelog')->where(['userId' => $user['user_id'], 'type' => ['neq', 11]])->sum('num');
            $allGoodsConsign = Db::name('goods_consignments')->where(['user_id' => $user['user_id'], 'five_status' => 1])->field('num,goods_price')->select();
            $sum = 0;
            foreach ($allGoodsConsign as $item) {
                $sum += $item['goods_price'] * $item['num'] * 0.8;
            }
            $userMoneyLeft = $sumBalanceLog + $sum;
            if(bccomp((string)$userMoneyLeft, $user['user_money'], 4) === -1){
                $wrongUsers[$user['user_id']] = [
                    '手机号' => $user['mobile'],
                    '计算出来的' => intval($userMoneyLeft),
                    '现在的余额' => intval($user['user_money']),
                    '差值' => intval($userMoneyLeft)- intval($user['user_money']),
                ];
                $lostTotal += intval($userMoneyLeft)- intval($user['user_money']);
            } else {
                $rightUsers[$user['user_id']] = [
                    '手机号' => $user['mobile'],
                    '计算出来的' => $userMoneyLeft,
                    '现在的余额' => $user['user_money'],
                ];
            }
        }
        echo "账面亏损：" .$lostTotal ;
        echo "<hr><br>";
//        echo '扣余额后亏损：'. $lostTotal2;
        echo "<hr><br>";

        dump($wrongUsers);
        echo "<b><hr><br></b>";
//        dump($rightUsers);
        die;
    }

    public function index()
    {
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;
            // 微信Jssdk 操作类 用分享朋友圈 JS
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();
            print_r($signPackage);
        */
//        if(!isMobile()){
//            $this->redirect(U('Home/Index/index'));
//        }
        $hot_goods = M('goods')->field('original_img,goods_name,goods_id')->where("is_hot=1 and is_on_sale=1")->order('sort')->limit(4)->cache(true, TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->field('image,id,mobile_name')->where('is_hot=1 and is_show=1')->order('sort_order')->limit(3)->cache(true, TPSHOP_CACHE_TIME)->select();
        $this->assign('thems', $thems);
        $this->assign('hot_goods', $hot_goods);

        //首页推荐商品及分类
        $favourite_goods = M('goods')
            ->field('e.goods_id,e.original_img,e.goods_name,e.shop_price,o.parent_id_path')
            ->alias('e')
            ->join('__GOODS_CATEGORY__ o', 'e.cat_id =o.id')
            ->where("e.is_recommend=1 and e.is_on_sale=1")
            ->order('e.sort')
            ->limit(5)
            ->cache(true, TPSHOP_CACHE_TIME)->select();
        foreach ($favourite_goods as $k => $v) {
            $classify = explode('_', $v['parent_id_path']);
            foreach ($classify as $ke => $vo) {
                $favourite_goods[$k]['classify'][] = M('goods_category')->field('mobile_name')->where(array('id' => $vo))->find();
            }
        }

        // 获取服务承诺信息
        $service_promise = Db::name('article_cat')
            ->alias('c')
            ->field('a.cat_id,a.article_id,a.title,a.content')
            ->join('__ARTICLE__ a', 'c.cat_id = a.cat_id')
            ->where(['c.cat_name ' => "服务承诺", 'a.is_open' => 1])
            ->cache(true, TPSHOP_CACHE_TIME)
            ->select();

        //获取购物车数量
        if (session('?user')) {
            $user = session('user');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($user['user_id']);
            $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
            $this->assign('goodstype', $userCartGoodsTypeNum);
        }
        //活动区
        $list = M('goods')
            ->where('type_id=6 and status=1 and is_recommend=1 and is_on_sale=1 AND UNIX_TIMESTAMP(agentend_time) <= UNIX_TIMESTAMP(NOW()) AND UNIX_TIMESTAMP(saleend_time) > UNIX_TIMESTAMP(NOW())')
            ->field(['goods_id', 'goods_name', 'shop_price', 'original_img'])->limit(4)->order('sort asc')->select();
        //批发区
        $list2 = M('goods')
            ->where('type_id=6 and status=0 and is_recommend=1 and is_on_sale=1 AND UNIX_TIMESTAMP(agentstart_time) <= UNIX_TIMESTAMP(NOW()) AND UNIX_TIMESTAMP(NOW()) <=  UNIX_TIMESTAMP(agentend_time)')
            ->field(['goods_id', 'goods_name', 'shop_price', 'original_img,agentend_time'])->limit(4)->order('sort asc')->select();
        //普通专区
        $list3 = M('goods')->where('type_id=0 and is_recommend=1 and is_on_sale=1 and prom_type = 0 ')->field(['goods_id', 'goods_name', 'shop_price', 'original_img'])->limit(4)->order('sort asc')->select();
        $this->assign('list', $list);
        $this->assign('list2', $list2);
        $this->assign('list3', $list3);
        $this->assign('service_promise', $service_promise);
        $this->assign('title', "首页");
        $this->assign('favourite_goods', $favourite_goods);

        //公告
        $ads = M('headline')->where('id', '>', 0)->select();
        $this->assign('ads', $ads);
        return $this->fetch();
    }

    public function agent()
    {
        $where = array(     //条件
            'g.is_on_sale' => 1,
            'g.prom_type' => 0,
            'g.type_id' => 6, //代理零售商品
            'g.status' => 0//代理状态1
        );
        $count = M('goods')->alias('g')->join('goods_setmeal s', 'g.goods_id = s.goods_id')->group('s.goods_id')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = Db::name('goods')->alias('g')
            ->join('goods_setmeal s', 's.goods_id = g.goods_id')
            ->field('max(trade_price) max,min(trade_price) min,g.goods_id,g.goods_name,agentend_time,sum(stock) stocks,g.original_img')
            ->where($where)
            ->where('UNIX_TIMESTAMP(agentstart_time) <= UNIX_TIMESTAMP(NOW()) AND UNIX_TIMESTAMP(NOW()) <  UNIX_TIMESTAMP(agentend_time)')
            ->group("g.goods_id")
            ->order('sort')
            ->select();
        if (empty($list[0]['goods_id'])) {
            $list = [];
        }
        $this->assign('list', $list);
        if (I('is_ajax')) {
            return $this->fetch('ajax_goods_list');//输出分页
        }
        return $this->fetch();
    }

    /**
     * 零售
     */
    public function retail()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'type_id' => 6, //代理零售商品
            'status' => 1 //代理状态
        );

//        $type = I('get.type');
//        if ($type == 'new') {
//            $order = 'shop_price';
//        } elseif ($type == 'comment') {
//            $order = 'sales_sum';
//        } else {
//            $order = 'goods_id';
//        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $pagesize = 10000000;  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = Db::name('goods')->alias('g')
            ->join('goods_setmeal s', 's.goods_id = g.goods_id')
            ->field('g.shop_price,g.goods_id,g.goods_name,g.original_img')
            ->where($where)
            ->where('UNIX_TIMESTAMP(agentend_time) <= UNIX_TIMESTAMP(NOW()) AND UNIX_TIMESTAMP(saleend_time) > UNIX_TIMESTAMP(NOW())')
            ->page($p, $pagesize)
            ->group('g.goods_id')
            ->order('g.sort')
            ->select();
        $this->assign('list', $list);

        if (I('is_ajax')) {
            return $this->fetch('ajax_todaygoods');//输出分页
        }
        return $this->fetch();
    }

    /**
     * 普通产品
     */
    public function ordinary()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            // 'is_recommend' => 1,
            'type_id' => 0
        );
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'original_img'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        if (IS_AJAX) {
            return $this->fetch('ajax_ordinary');
        }
        return $this->fetch();
    }

    /**
     * 推荐产品
     */
    public function recommend()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'is_recommend' => 1,
            'type_id' => 0
        );
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'original_img'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        if (IS_AJAX) {
            return $this->fetch('ajax_ordinary');
        }
        return $this->fetch();
    }

    public function _index_goods()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'is_recommend' => 1,
            'type_id' => 0
        );
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'original_img'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist()
    {
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach ($arr as $key => $val) {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";
        }
    }

    /**
     * 商品列表页
     */
    public function goodsList()
    {
        $id = I('get.id/d', 0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function ajaxGetMore()
    {
        $p = I('p/d', 1);
        $where = ['is_recommend' => 1, 'is_on_sale' => 1, 'virtual_indate' => ['exp', ' = 0 OR virtual_indate > ' . time()]];
        $favourite_goods = Db::name('goods')->where($where)->order('goods_id DESC')->page($p, C('PAGESIZE'))->cache(true, TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        return $this->fetch();
    }

    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig()
    {
        $askUrl = I('askUrl');//分享URL
        $weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
        $signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
        if ($signPackage) {
            $this->ajaxReturn($signPackage, 'JSON');
        } else {
            return false;
        }
    }

    public function repair_index()
    {
        $repairs = Db::name("repair_cat")->where(array('parent_id' => 0))->select();
        $this->assign('repairs', $repairs);
        return $this->fetch();

    }

    /*
     * 服务承诺
     * */
    public function service_promise()
    {
        // 获取服务承诺信息
        $article_id = I('article_id');
        $service_promise = Db::name('article')
            ->where(['article_id' => $article_id])
            ->find();
        $service_promise['content'] = html_entity_decode($service_promise['content']);
//        dump($service_promise);exit;
        $this->assign('promise', $service_promise);
        return $this->fetch();
    }


    public function test()
    {
        $wechatLogic = new \app\common\logic\WechatLogic();
        $res = $wechatLogic->sendMoney();
        dump($res);
        die;
    }

}