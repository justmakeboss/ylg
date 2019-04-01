<?php

namespace app\mobile\controller;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\SpecGoodsPrice;
use think\AjaxPage;
use think\image\Exception;
use think\Page;
use think\Db;

use app\common\logic\CartLogic;//购物车

class Goods extends MobileBase
{
    public function index()
    {
//        dump(getwxconfig());

        // $this->show('<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} body{ background: #fff; font-family: "微软雅黑"; color: #333;font-size:24px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.8em; font-size: 36px } a,a:hover,{color:blue;}</style><div style="padding: 24px 48px;"> <h1>:)</h1><p>欢迎使用 <b>ThinkPHP</b>！</p><br/>版本 V{$Think.version}</div><script type="text/javascript" src="http://ad.topthink.com/public/static/client.js"></script><thinkad id="ad_55e75dfae343f5a1"></thinkad><script type="text/javascript" src="http://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script>','utf-8');
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
     * 商品列表页
     */
    public function goodsList()
    {
        $filter_param = array(); // 筛选数组
        $id = I('id/d', 1); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        $spec = I('spec', 0); // 规格
        $attr = I('attr', ''); // 属性
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'asc'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入筛选条件中
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
        $spec && ($filter_param['spec'] = $spec); //加入筛选条件中
        $attr && ($filter_param['attr'] = $attr); //加入筛选条件中
        $price && ($filter_param['price'] = $price); //加入筛选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        $goods_where = ['is_on_sale' => 1, 'exchange_integral' => 0, 'cat_id' => ['in', $cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField("goods_id", true);

        // 过滤筛选的结果集里面找商品
        if ($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
        }
        if ($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_2); // 获取多个筛选条件的结果 的交集
        }
        if ($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个筛选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if ($sel) {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel, $cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
        }

        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的筛选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 筛选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList'); // 获取指定分类下的筛选品牌
        $filter_spec = $goodsLogic->get_filter_spec($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选规格
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选属性

        $count = count($filter_goods_id);
        $page = new Page($count, C('PAGESIZE'));
        if ($count > 0) {
            $goods_list = M('goods')
                ->field('e.*,o.parent_id_path')
                ->alias('e')
                ->join('__GOODS_CATEGORY__ o', 'e.cat_id =o.id')
                ->where("goods_id", "in", implode(',', $filter_goods_id))
                ->order("$sort $sort_asc")
                ->limit($page->firstRow . ',' . $page->listRows)
                ->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        if (!empty($goods_list)) {

            foreach ($goods_list as $k => $v) {
                $classify = explode('_', $v['parent_id_path']);

                foreach ($classify as $ke => $vo) {
                    $goods_list[$k]['classify'][] = M('goods_category')->field('mobile_name')->where(array('id' => $vo))->find();
                }
            }
        }


        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list', $goods_list);
//        dump($goods_list);die;
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 筛选菜单
        $this->assign('filter_spec', $filter_spec);  // 筛选规格
        $this->assign('filter_attr', $filter_attr);  // 筛选属性
        $this->assign('filter_brand', $filter_brand);// 列表页筛选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 筛选的价格期间
        $this->assign('goodsCate', $goodsCate);
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 筛选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('web_title', '商品列表');
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON', false);
        if (input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList()
    {
        $where = '';

        $cat_id = I("id/d", 0); // 所选择的商品分类id
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " WHERE cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count, 10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit " . $page->firstRow . ',' . $page->listRows;
        $list = DB::query("select *  from __PREFIX__goods $where $order $limit");

        $this->assign('lists', $list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
        exit($html);
    }

//    public function goods_info()
//    {
//
//    }
    /**
     * 商品详情页
     */
    public function goodsInfo(){
        C('TOKEN_ON',true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        $goods = Db::name('goods')->where('goods_id',$goods_id)->find();
         //代理产品和零售产品需要传个参数过来作为判断  没有参数 则是自营产品

//        $goodsModel = new \app\common\model\Goods();
        if($goods['type_id'] == 6 && $goods['status'] == 0){
            $goods = Db::name('goods')->alias('g')
                ->join('goods_setmeal s','s.goods_id = g.goods_id')
                ->field('g.*,max(trade_price) max,min(trade_price) min')
                ->where('g.goods_id',$goods_id)
//            ->page($p, $pagesize)
                ->find();
            $setmeal = Db::name('goods_setmeal')->where('goods_id',$goods_id)->select();
            $this->assign('setmeal',$setmeal);
        }else{
            $goods = Db::name('goods')->alias('g')
                ->where('g.goods_id',$goods_id)
//            ->page($p, $pagesize)
                ->find();
        }

        if(empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual']==1 && $goods['virtual_indate'] <= time())){
            $this->error('此商品不存在或者已下架');
        }
        $goodsPromFactory = new GoodsPromFactory();
        if (!empty($goods['prom_id']) && $goodsPromFactory->checkPromType($goods['prom_type'])) {
            $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);//这里会自动更新商品活动状态，所以商品需要重新查询
            $goods = $goodsPromLogic->getGoodsInfo();//上面更新商品信息后需要查询
        }
        if($goods['prom_type'] == 1){
            $flash_sale = Db::name('flash_sale')->where("goods_id = {$goods['goods_id']}")->find();
            $goods['shop_price'] = $flash_sale['price'];
            $goods['store_count'] = $flash_sale['goods_num'];
            $goods['sales_sum'] = $flash_sale['order_num'];
        }

        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册

        if(empty($goods['type_id'])){
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表

        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
      	$goods['sale_num'] = M('order_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
        //当前用户收藏
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数

        //分享描述
        $desc = '';
        foreach($filter_spec as $key =>$val){
            foreach($val as $v){
                $desc .=$v['item'].'  ';
            }
        }


        // 商品详情页评论
        $commentType = 1;
        $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
        $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        
        $commentCount = M('Comment')->where($where)->count();
        $page_count = C('PAGESIZE');
        $commentList = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->field("*,FROM_UNIXTIME(add_time,'%Y-%m-%d %H:%i:%s') as Time")
            ->order("add_time desc")
            ->limit(1)
            ->select();
        foreach ($commentList as $key => $value) {
            $commentImg = unserialize($value['img']);
            $commentList[$key]['commentImg'] = $commentImg;
        }
        }

        $this->assign('commentCount',$commentCount);//规格参数
        $this->assign('commentList',$commentList);//规格参数
        // END 7-10


//        $this->assign('desc',$desc);
        $request=  \think\Request::instance();
        $action=$request->action();
        $this->assign('action',$action);
        $this->assign('collect',$collect);
        $this->assign('commentStatistics',$commentStatistics);//评论概览
        $this->assign('goods_attribute',$goods_attribute);//属性值
        $this->assign('goods_attr_list',$goods_attr_list);//属性列表
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('goods',$goods);
//        $this->assign('goods',$goods->toArray());
        $point_rate = tpCache('shopping.point_rate');
        $this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
        $this->assign('point_rate', $point_rate);
        if($goods['type_id'] == 6 && $goods['status'] == 0){
            return $this->fetch('goods_info');
        }elseif($goods['type_id'] == 6 && $goods['status'] == 1){
            return $this->fetch();
        }else{
            return $this->fetch();

        }
    }

    // 购物车商品数量
    public function cartNum(){

        $user = session('user');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user['user_id']);
        $cartNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数

        return $cartNum;

    }

    public function activity(){
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id/d');//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if($item_id){
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',true);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,$specGoodsPrice);
            }else{
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
            }
            //检查活动是否有效
            if($goodsPromLogic->checkActivityIsAble()){
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品参与活动','result'=>['goods'=>$goods]]);
            }else{
                if(!empty($goods['price_ladder'])){
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($goods['price_ladder']);
                    $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
                }
                $goods['activity_is_on'] = 0;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
            }
        }
        if(!empty($goods['price_ladder'])){
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
    }

    /*
     * 商品评论
     */
    public function comment(){
        $goods_id = I("goods_id/d",0);
        $this->assign('goods_id',$goods_id);
        return $this->fetch();
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }
        $count = M('Comment')->where($where)->count();
        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $list[$k]['service_rank'] =star($v['service_rank']); // 评分
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('commentlist', $list);// 商品评论
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('p', I('p'));//页数
        return $this->fetch();
    }

    /*
     * 获取商品规格
     */
    public function goodsAttr(){
        $goods_id = I("get.goods_id/d",0);
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);
        return $this->fetch();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $rank= I('get.rank');
        //以兑换量（购买量）排序
        if($rank == 'num'){
            $ranktype = 'sales_sum';
            $order = 'desc';
        }
        //以需要积分排序
        if($rank == 'integral'){
            $ranktype = 'exchange_integral';
            $order = 'desc';
        }
        $point_rate = tpCache('shopping.point_rate');
        $goods_where = array(
            'is_on_sale' => 1,  //是否上架
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));

        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandson($cat_id));
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($rank == 'exchange' && !empty($user_id)) {
            //获取用户积分
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }
        $goods_where['exchange_integral'] =  $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->order($ranktype ,$order)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('totalPages',$page->totalPages);//总页数
        if(IS_AJAX){
            return $this->fetch('ajaxIntegralMall'); //获取更多
        }
        return $this->fetch();
    }

     /**
     * 商品搜索列表页
     */
    public function search(){
    	$filter_param = array(); // 筛选数组
    	$id = I('get.id/d',0); // 当前分类id
    	$brand_id = I('brand_id/d',0);
    	$sort = I('sort','goods_id'); // 排序
    	$sort_asc = I('sort_asc','asc'); // 排序
    	$price = I('price',''); // 价钱
    	$start_price = trim(I('start_price','0')); // 输入框价钱
    	$end_price = trim(I('end_price','0')); // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
    	$filter_param['id'] = $id; //加入筛选条件中
    	$brand_id  && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
    	$price  && ($filter_param['price'] = $price); //加入筛选条件中
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入筛选条件中
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        $where['type_id'] = 0;
        $where['exchange_integral'] = 0;//不检索积分商品
        if($qtype){
        	$filter_param['qtype'] = $qtype;
        	$where[$qtype] = 1;
        }
        if($q) $where['goods_name'] = array('like','%'.$q.'%');

    	$goodsLogic = new GoodsLogic();
    	$filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id",true);

    	// 过滤筛选的结果集里面找商品
    	if($brand_id || $price)// 品牌或者价格
    	{
    		$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个筛选条件的结果 的交集
    	}

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的筛选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 筛选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search'); // 获取指定分类下的筛选品牌

    	$count = count($filter_goods_id);
    	$page = new Page($count,12);
    	if($count > 0)
    	{
    		$goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
    		$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    		if($filter_goods_id2)
    			$goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    	}
    	$goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    	$this->assign('goods_list',$goods_list);
    	$this->assign('goods_category',$goods_category);
    	$this->assign('goods_images',$goods_images);  // 相册图片
    	$this->assign('filter_menu',$filter_menu);  // 筛选菜单
    	$this->assign('filter_brand',$filter_brand);// 列表页筛选属性 - 商品品牌
    	$this->assign('filter_price',$filter_price);// 筛选的价格期间
    	$this->assign('filter_param',$filter_param); // 筛选条件
    	$this->assign('page',$page);// 赋值分页输出
        $this->assign('web_title','商品搜索');
    	$this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
    	C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品搜索列表页
     */
    public function ajaxSearch()
    {
        $this->assign('web_title','商品搜索');
        return $this->fetch();
    }

    /**
     * 品牌街
     */
    public function brandstreet()
    {
        $getnum = 9;   //取出数量
        $goods=M('goods')->where(array('is_recommend'=>1,'is_on_sale'=>1))->page(1,$getnum)->cache(true,TPSHOP_CACHE_TIME)->select(); //推荐商品
        for($i=0;$i<($getnum/3);$i++){
            //3条记录为一组
            $recommend_goods[] = array_slice($goods,$i*3,3);
        }
        $where = array(
            'is_hot' => 1,  //1为推荐品牌
        );
        $count = M('brand')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count,20);
        $brand_list = M('brand')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        $this->assign('recommend_goods',$recommend_goods);  //品牌列表
        $this->assign('brand_list',$brand_list);            //推荐商品
        $this->assign('listRows',$Page->listRows);
        if(I('is_ajax')){
            return $this->fetch('ajaxBrandstreet');
        }
        return $this->fetch();
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id){
        $goods_id = I('goods_id/d');
        $goodsLogic = new GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'),$goods_id);
        exit(json_encode($result));
    }

    public function satest()
    {
        throw new Exception('商品已分享');
    }

    //用户分享商品
    public function share_goods(){
        $user_id = cookie('user_id');
        $goods_id = I('goods_id/d');
        Db::startTrans();
        try{
            $goods_integral = tpCache('basic.goods_integral'); // 会员分享赠送积分
            $count = M('share')->where(["user_id"=>$user_id,"goods_id"=>$goods_id])->count();
            if ($count > 0)
                throw new Exception('商品已分享');
                //return json_encode(array('status' => 0, 'msg' => '商品已分享', 'result' => ''));
            $goods_info = M('goods')->where('goods_id',$goods_id)->field('goods_name,original_img,shop_price')->find();
            $goods_info['goods_id']=$goods_id;
            $goods_info['user_id']=$user_id;
            $goods_info['share_t']=time();
            $goods_info['integral']= $goods_integral;  //分享积分
            $re = M('share')->insert($goods_info);
            if($re){
                M('users')->where(['user_id'=>$user_id])->setInc('pay_points',$goods_integral);
                echo json_encode(array('status' => 1, 'msg' => '分享成功！', 'result' =>'' ));
                // 提交事务
                Db::commit();
            }else{
                throw new Exception('系统繁忙,稍后再试！');
            }

        }catch (\Exception $e) {
            echo json_encode(array('status' => 0, 'msg' => $e->getMessage()));
            // 回滚事务
            Db::rollback();
        }

    }


}