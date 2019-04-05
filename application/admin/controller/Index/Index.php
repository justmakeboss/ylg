<?php
namespace app\admin\controller\Index;

use app\admin\model\HeadLine;
use think\Page;
use app\admin\controller\Base;
use think\Verify;
use think\Db;
use think\Session;
use \think\Request;

class Index extends Base
{
    public function ad()
    {
        $headline = new HeadLine();
        $headlines = $headline->where('id', '>', 0)->select();
        $this->assign('headlines', $headlines);
        return $this->fetch();
    }

    public function create()
    {
        $data = I('post.');
        $headline = new HeadLine;
        $data['created_at'] = date('Y-m-d H:i:s');
        $headline->data($data);
        $headline->save();
        if($headline->id) {
            return ['code' => 200, 'msg' => '添加成功'];
        } else {
            return ['code' => 422, 'msg' => '添加失败'];
        }
    }

    public function edit()
    {
        $id = I('post.id');
        $headlineModel = new HeadLine();
        $headline = $headlineModel->find(['id' => $id]);
        $this->assign('headline', $headline);
        return $this->fetch();
    }

    public function update(Request $request)
    {
        $data = $request->post();
        $headlineModel = new HeadLine();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $r = $headlineModel->save($data, ['id' => $request->post('id')]);
        if($r === false) {
            return ['code' => 422, 'msg' => '更新失败'];
        } else {

            return ['code' => 200, 'msg' => '更新成功'];
        }
    }

    public function delete(Request $request)
    {
        $headline = HeadLine::get($request->get('id'));
        $r = $headline->delete();
        if($r) {
            return ['code' => 200, 'msg' => '删除成功', 'data' => ['id' => $request->get('id')]];
        } else {
            return ['code' => 422, 'msg' => '删除失败'];
        }
    }

}