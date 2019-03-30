<?php

namespace app\common\model;

use think\Db;
use think\Model;

class UserLevel extends Model
{
    protected $table = 'tp_user_level';

    public function getLevelInfoByUserLevel($userLevel)
    {
        return $this->find($userLevel);
    }
}
