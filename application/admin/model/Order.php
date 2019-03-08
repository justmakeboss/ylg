<?php

namespace app\admin\model;
use think\Model;
class Order extends Model {


    /**
     * 获取当月活动区消费次数
     * @param $userId
     * @return int|string
     */
    public function getUserCurMonthShoppingTimes($userId)
    {
        $currMonthFirstDay = strtotime(getCurMonthFirstDay(date('Y-m-d')));
        $currMonthLastDay = strtotime(getCurMonthLastDay(date('Y-m-d')));
        return M('order')->where(['user_id' => $userId,'pay_status' => 1,'pay_time' => ['>=', $currMonthFirstDay], 'pay_time' => ['<', $currMonthLastDay], 'type' => 1])->count();
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
    }
}
