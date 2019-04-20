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
        file_put_contents('/home/ylg/1.txt', time(),8);
        // 输出到日志文件
        $output->writeln("TestCommand:");
        // 定时器需要执行的内容

        $goodsCron = Db::name('goods_crontab')->where(['status' => 0])->select();

        foreach ($goodsCron as $item) {





        }





        $output->writeln("end....");
    }
}