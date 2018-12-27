<?php
namespace Deployer;
require 'recipe/yii.php';
require 'recipe/symfony.php';
// 预定的安装目录
$path = '/home/deployer/yii2';
// Configuration
set('ssh_type', 'native');   //录远程主机使用的方式，有三种：phpseclib（默认方式）、native、ext-ssh2
set('ssh_multiplexing', true);   // 是否开启ssh通道复用技术（开启可以降低服务器和本地负载，并提升速度）
set('keep_releases', 10);   //报错10个之前版本，设置为-1表示一直保存历史版本
set('repository', 'git@github.com:xiachaoqing/deployer_yii2.git');   // 代码仓库的地址，只支持git
set('branch', 'master');    // 发布代码时候默认使用的分支
set('shared_files', []);    // 共享文件列表   这里面列出的文件会被移动到项目根目录的shared目录下，并做软链
set('shared_dirs', []);     // 共享目录    同上
set('writable_mode', 'chmod');  // 采用哪种方式控制可写权限，有4中：chown、chgrp、chmod、acl（默认方式）
set('writable_chmod_mode', '0755'); // 当使用chmod控制可写权限的时候，赋予的可写权限值
set('writable_dirs', []);   // 可写目录   规定那些目录是需要可以被web server写入的
set('clear_path', []);  // 设置在代码发布的时候需要被删除的目录
set('http_user', 'nginx');  // web server的用户，一般不用设置，deployer会自动判断
set('release_name', function () {   // 设置发布版名称，这里优先使用tag作为名称，不传的话会使用日期+时间表示发布时间
    if (input()->hasOption('tag')) {
        return input()->getOption('tag');
    }
    return date('Ymd-H:i');
});
add('shared_files', []);   // 增加共享文件列表 
add('shared_dirs', []);   //增加共享目录
add('writable_dirs', []);   // 增加可写目录   规定那些目录是需要可以被web server写入的
// 指定git仓库的位置，如果是私有的，可以根据HTTP协议设置登录用户
// Servers
// 针对每个服务器可以单独设置参数，设置的参数会覆盖全局的参数
server('prod', '39.105.129.6',8022)
    ->user('deployer')
    ->password('123456')
    ->set('deploy_path', $path)   // 代码部署目录，注意：你的webserver，比如nginx，设置的root目录应该是/var/www/tb/current，因为current是一个指向当前线上实际使用的版本的软链
    ->set('branch', 'master')   // 指定发往这个服务器的分支，会覆盖全局设置的branch参数
    ->set('http_user', 'www-data') // 这个与 nginx 里的配置一致
    ->set('extra_stuff', '-t') // 随意指定其他什么参数
    ->stage('prod'); // 标识该服务器类型，用于服务器分组

server('beta', '39.105.129.6',8022)
    ->user('deployer')
    ->password('123456')
    ->set('deploy_path', '/home/deployer/yii2/test')
    ->set('branch', 'beta')   // 测试环境使用beta分支
    ->set('http_user', 'www-data') // 这个与 nginx 里的配置一致
    ->set('extra_stuff', '-t') // 随意指定其他什么参数
    ->stage('beta');   // 放在beta分组


// Tasks
// 配置的任务
task('success', function () {
    Deployer::setDefault('terminate_message', '<info>发布成功!</info>');
})->once()->setPrivate();   // 增加once调用那么这个任务将会在本地执行，而非远端服务器，并且只执行一次
 
// desc('重启php-fpm');    // 可以给任务增加一个描述，在执行dep list的时候将能看到这个描述
// task('php-fpm:restart', function () {
//     // run('systemctl restart php-fpm.service');  // run函数定义在服务器执行的操作，通常是一个shell命令，可以有返回值，返回命令打印
//     run('/etc/init.d/php5-fpm restart');
// });     // 聪明如你一定发现了，可以用run函数制作一些批量管理服务器的任务，比如批量重载所有的nginx配置文件、批量执行服务器上的脚本等
 
// after('deploy:symlink', 'php-fpm:restart'); // 钩子函数，表示执行完设置软链任务之后执行php-fpm重启任务
 
desc('发布项目');
task('deploy', [    // 可以设置复合任务，第二个参数是这个复合任务包括的所有子任务，将会依次执行
    'deploy:prepare',   // 发布前准备，检查一些需要的目录是否存在，不存在将会自动创建
    'deploy:lock',  // 生成锁文件，避免同时在一台服务器上执行两个发布流程，造成状态混乱
    'deploy:release',   // 创建代码存放目录
    'deploy:update_code',   // 更新代码，通常是git，你也可以重写这个task，使用upload方法，采用sftp方式上传
    'deploy:shared',    // 处理共享文件或目录
    'deploy:writable',  // 设置目录可写权限
    'deploy:vendors',   // 根据composer配置，安装依赖
    'deploy:clear_paths',   // 根据设置的clear_path参数，执行删除操作
    'deploy:symlink',   // 设置符号连接到最新更新的代码，线上此时访问的就是本次发布的代码了
    'deploy:unlock',     // 删除锁文件，以便下次发布
    'cleanup',  // 根据keep_releases参数，清楚过老的版本，释放服务器磁盘空间
    'success'   // 执行成功任务，上面自己定义的，一般用来做提示
]);
// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');