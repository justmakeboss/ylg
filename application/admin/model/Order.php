<?php

namespace app\admin\model;
use think\Model;
use think\Db;
class Order extends Model {


    /**
     * 获取当月活动区消费次数
     * @param $userId
     * @return int|string
     */
    public function getUserCurMonthShoppingTimes($userId)
    {
        $currMonthFirstDay = date('Y-m-01');
        return $this->where(['user_id' => $userId,'order_status' => 4,'pay_time' => ['>=', strtotime(date('Y-m-01'))], 'pay_time' => ['<', strtotime("{$currMonthFirstDay} + 1 month")], 'type' => 1])->count();
    }

    /**
     * 获取活动区消费次数根据起止时间
     * @param $userId
     * @param $start
     * @param $end
     * @return int|string
     */
    public function getUserShoppingTimesByTime($userId, $start, $end)
    {
        return $this->where(['user_id' => $userId,'order_status' => 4,'pay_time' => ['>=', $start], 'pay_time' => ['<', $end], 'type' => 1])->count();
    }
}
