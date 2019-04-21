<?php
namespace app\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
class Test extends Command
{
    // 配置定时器的信息
    protected function configure()
    {
        $this->setName('test')
            ->setDescription('商品代理任务');
    }
    protected function execute(Input $input, Output $output)
    {
        // 输出到日志文件
        $output->writeln("TestCommand:");
        $users = Db::name('users')->field('user_id')->select();
        foreach ($users as $user) {
            forzenss($user['user_id']);
            forzens($user['user_id']);
        }
        $output->writeln("end....");
    }
}