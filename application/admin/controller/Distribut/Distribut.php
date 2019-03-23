<?php
namespace app\admin\controller\Distribut;

use app\admin\controller\Base;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\AjaxPage;
use think\Controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;

class Distribut extends Base
{
    /**
     * 分销设置
     */
    public function index(){
        /*配置列表*/
        $group_list = [
            'role_info' => '角色分销',
            //'prize'     => '奖项设置',
            'settlement'=> '分销设置',
            'levels'    => '上下线关系及分销资格',
            'extension' => '推广中心',
            //'notice' => '通知设置',
            'agreement' => '分销协议',
        ];
        $this->assign('group_list',$group_list);
        $inc_type =  I('get.inc_type','role_info');
        $this->assign('inc_type',$inc_type);
        $config = distributCache($inc_type);
        if($inc_type == 'role_info'){
            $Ad =  M('user_level');
            $p = $this->request->param('p');
            $res = $Ad->order('level_id')->page($p.',10')->select();
            if($res){
                foreach ($res as $val){
                    $list[] = $val;
                }
            }
            $this->assign('list',$list);
            $count = $Ad->count();
            $Page = new Page($count,10);
            $show = $Page->show();
            $this->assign('page',$show);
        }
        $this->assign(  'config',$config);//当前配置项
        //C('TOKEN_ON',false);
        return $this->fetch($inc_type);
    }
    //分销商品列表
    public function goods_list()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }

    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        $where .= " and commission > 0 ";
        $count = M('Goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);
        return $this->fetch();
    }
    /*
     * 新增修改配置
     */
    public function handle()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
         //dump($param);exit;
        //unset($param['__hash__']);
        unset($param['inc_type']);
        if($inc_type == 'agreement'){
            $param['content'] = base64_encode($param['content']);
        }
        distributCache($inc_type,$param);
        $this->success("操作成功",U('Distribut.Distribut/index',array('inc_type'=>$inc_type)));
    }
    /**
     * 分销设置
     */
    public function set()
    {
        header("Location:" . U('Admin/Systems.Systems/index', array('inc_type' => 'distribut')));
        exit;
    }

    /**分销关系
     * @return mixed
     */
    public function tree()
    {
        //$where = 'is_distribut = 1';
        $where = 'first_leader = 0';
        if ($this->request->param('user_id')) {
            $where = "user_id = '{$this->request->param('user_id')}'";
            $where2 = "mobile = '{$this->request->param('user_id')}'";
            // $list = M('users')->where($where)->orWhere($where2)->select();
            $list = Db::query("select * from  tp_users where " . $where . ' or '. $where2);

        } else {
            $list = M('users')->where($where)->select();
        }
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $id = $this->request->param('id');
        $userlevel = $this->request->param('userlevel');
        $userlevel_field = '';
        $userlevel = "first_leader";
        if ($userlevel == "first_leader") {
            $userlevel_field = "second_leader";
        } else if ($userlevel == "second_leader") {
            $userlevel_field = "third_leader";
        }
        $where = '';
        if ($userlevel == 'first_leader') $where .= "first_leader =" . $id;
        if ($userlevel == 'second_leader') $where .= "second_leader =" . $id;
        if ($userlevel == 'third_leader') $where .= "third_leader =" . $id;
        $list = M('users')->where($where)->select();
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['user_level'] = $userlevel_field;
            $_list[] = $_t;
        }
        $this->assign('list', $_list);
        return $this->fetch();
    }

    //分销商列表
    public function distributor_list()
    {
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function distributorajaxindex()
    {
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');
        $condition['is_distribut'] = 1;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }
        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    //分成日志
    public function rebate_log()
    {

        return $this->fetch();
    }

    public function ajax_rebate_log(){
        $input = I('');
        // dump($input);die;
        $maid_time = distributCache('settlement.maid_time');
        if($maid_time == 2){
            $msg = '已收货' ;
        }else{
            $msg = $maid_time ? '已发货' : '已支付' ;
        }
        $search_key = $input['search_key'];//搜索方式
        $search_value= $input['search_value'];//input框的值
        $where =  array();
        if($search_key && $search_value){
            // if($search_key == 'user_id'){
            //     $userdata = M('users')->where(['mobile' => $search_value])->field(array('user_id'))->select();
            //     $user_string = '';
            //     foreach ($userdata as $k => $v) {
            //          $user_string = $v['user_id'].',';
            //     }
            //     $where[$search_key] =  array('in',$user_string);
            // else {
                $where[$search_key] =  array('like','%'.$search_value.'%');//字段名=。。。
            // }
        }
        $count = M('rebate_log')->where($where)->count();
        $Page = new AjaxPage($count, 10);
        $lists = M('rebate_log')->order("field(status,2,1,0,3,4)")->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        // dump($lists);die;
        $_lists = array();
        foreach ($lists as $key => $val) {
            $_t = $val;
            $_t['user_name'] = M('users')->where(['user_id'=>$val['user_id']])->value('nickname');
            // $status_string = ['未付款','已付款','等待分成(已收货)','已分成','已取消'];
            $status_string =[0=>"<font color='#DC143C'>未付款</font>",1=>"<font color='#436EEE'>已付款</font>",2=>"<font color='#00CD00'>等待分成($msg)</font>",3=>"<font color='#969696'>已分成</font>",4=>"<font color='#BFEFFF'>已取消</font>"];
            // $_t['is_off'] = $val['status'] == 3 ? "<font color='red'>已分成</font>" : '确定分成';
            $_t['statuss'] = $status_string[$_t['status']] ;
            $_t['yesurl'] = url('Distribut/yesurl', array('id' => $val['id']));
            $_lists[] = $_t;
        }
         $show = $Page->show();
        // dump($_lists);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('lists', $_lists);
        return $this->fetch();
    }

    //确定分成
    public function yesurl(){
        $id = input('id/d');
        $rebate = Db::name('rebate_log')->where(['id'=>$id])->find();

        if(!$rebate)
            $this->error('该分成记录不存在');
        if($rebate['status'] != 2)
            $this->error('不能修改分成记录');
        $maid_time = distributCache('settlement.maid_time');
        if($maid_time == 2){
            if(!$rebate['confirm'] || time()< $rebate['confirm'] + distributCache('settlement.date'))
                $this->error('确认收货后，未达到规定时间进行确认分成。'.distributCache('settlement.date').'天');
        }elseif($maid_time == 1){
            if(!$rebate['confirm'] || time()< $rebate['confirm'] + distributCache('settlement.date'))
                $this->error('确认发货后，未达到规定时间进行确认分成。'.distributCache('settlement.date').'天');
        }else{
            if(!$rebate['pay_time'] || time()< $rebate['pay_time'] + distributCache('settlement.date'))
                $this->error('订单支付后，未达到规定时间进行确认分成。'.distributCache('settlement.date').'天');
        }

        Db::startTrans();

        //更新记录
        $data['confirm_time'] = time();
        $data['status'] = 3;
        $result = Db::name('rebate_log')->where(['id'=>$id])->update($data);
        //更新分成金额
        $user_logic = new UsersLogic();
        $rebate_log = ['desc'=>'分佣获得余额','order_sn'=>$rebate['order_sn'],'order_id'=>$rebate['order_id']];
        $set_user = $user_logic->setAccountOrPoints($rebate['user_id'],'account',$rebate['money'],$rebate_log);
        //更新用户所累积的分佣金额
        $user = Users::get($rebate['user_id']);
        $user->distribut_money += $rebate['money'];
        $user_add = $user->save();

        //添加账户金额记录
        if($set_user !== true || !$result || !$user_add)
        {
            Db::rollback();
            $this->error('操作失败，稍后再试！');
        }

        Db::commit();
        $this->Success('操作成功');
    }

    public function prize(){
            $prize_list = [
                'first_prize' => '直推奖',
                'team_prize' => '团队奖',
                'special_prize'=>'产品分销奖',
                'management_prize'=>'管理奖',
                'market_prize'=>'市场补助奖',
                'areas_share_prize'=>'产品区域分红奖',
                'car_home_prize'=>'车房奖',
                'discount_prize'=>'身份折扣奖',
                'general_prize'=>'普通分销奖',
                'recommended_prize'=>'推荐奖',
            ];
            $level_id = I('id');
            $prize_info =  I('get.prize_info','first_prize');
            $config = distributCache($prize_info.'-'.$level_id);
            if ($prize_info == 'recommended_prize') {
                //获取角色
                $user_level =  Db::name('user_level')->order('level_id')->select();
                $this->assign('user_level',$user_level);
                $config_arr = unserialize($config[0]);
                $config = [];
                $config['recommended_prize_switch'] = $config_arr['recommended_prize_switch'];
                unset($config_arr['recommended_prize_switch']);
                $len = count($config_arr['recommend_identity']);
                for ($i = 0; $i<$len; $i++){
                    $prize_arr[$i]['recommend_identity'] = $config_arr['recommend_identity'][$i];
                    $prize_arr[$i]['first_leader'] = $config_arr['first_leader'][$i];
                    $prize_arr[$i]['second_leader'] = $config_arr['second_leader'][$i];
                }

                $this->assign('prize_arr',$prize_arr);
                $this->assign('len',$len);
            }
            $arr = ['general_prize', 'special_prize','management_prize'];
            if (in_array($prize_info,$arr)){
                $config['general_prize_level'] = unserialize($config['general_prize_level']);
                $config['special_prize_level'] = unserialize($config['special_prize_level']);
            }
//            dump($config);exit;
            $this->assign('prize_info',$prize_info);
            $this->assign('id',$level_id);
            $this->assign('prize_list', $prize_list);
            $this->assign('config',$config);//当前配置项
            // 分销奖项
            return $this->fetch($prize_info);
    }

    /*
     * 新增修改配置
     */
    public function ajax_prize()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
        unset($param['inc_type']);
        $logo = explode('-',$inc_type);
        if (in_array('recommended_prize',$logo)){
            unset($param['recommendgrade']);
            $params[] = serialize($param);
            $param = $params;
        }
        // 判断奖项，对应数组序列化存
        if (in_array('general_prize', $logo)){
            $param['general_prize_level'] = serialize($param['general_prize_level']);
        } elseif( in_array('special_prize', $logo)) {
            $param['special_prize_level'] = serialize($param['special_prize_level']);
        }
        distributCache($inc_type,$param);
        exit(json_encode(['code' => 1]));
    }
    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $prom_id = input('prom_id');
        $tpl = input('tpl', 'search_goods');
        $where = ['is_on_sale' => 1,'is_new'=>0, 'store_count' => ['gt', 0],'is_virtual'=>0,'exchange_integral'=>0];
        $prom_type = input('prom_type/d');
        if($goods_id){
            $where['goods_id'] = ['<>',$goods_id];
        }
        if($intro){
            $where[$intro] = 1;
        }
        if($cat_id){
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in',implode(',', $grandson_ids)];
        }
        if ($brand_id) {
            $where['brand_id'] = $brand_id;
        }
        if($keywords){
            $where['goods_name|keywords'] = ['like','%'.$keywords.'%'];
        }
        $Goods = new Goods();
        $count = $Goods->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_type', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6,7])){
                //抢购，团购，拼单
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_type',0);
            }else{
                $query->where('prom_type',0);
            }
        })->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_type', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6,7])){
                //抢购，团购，拼单
                $query->where('prom_type','in' ,[0,$prom_type]);
            }else{
                $query->where('prom_type',0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic;
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch($tpl);
    }
}