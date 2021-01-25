## Guarder
这是一个报警进程管理器。
父进程负责管理启动、重启、停止、回收子进程和接受web的请求。子进程负责定时任务内监控报警并且如果出现非预期情况则发送报警。


### 配置文件
启动项目之前需要配置好`guarder.ini`和`receiver.ini`。
- `guarder.ini`：主要是配置项目相关的参数，依次在`src/config/guarder.ini`,`/etc/guarder.ini`和`/etc/config/guarder.ini`,`/etc/guarder/guarder.ini`查找并且读取。

- `receiver.ini`：默认是这个文件名，主要是配置通知相关的参数。可以通过在`guarder.ini`中配置`receiver`参数指定，默认是读取`src/config/receiver.ini`文件。

- `*.ini`：主进程启动时读取子任务配置文件并且启动一个子进程管理。在`guarder.ini`中参数`file`指定的路径。

guarder.ini
```ini
user = username
passwd = passwd
host = 127.0.0.1
port = 80
; 保存父进程ID文件
ppid_file = /tmp/guarder.pid
; 任务配置
files = ./task/*.ini
; 接收通知配置
receiver = ./receiver.ini
```

receiver.ini
```ini
[common]
; 多个邮件地址发送
mailers[] = xxx@163.com
; 多个手机号码发送逗号隔开
phones = 
; 0不发送，1邮件，2云片短信，3微信，100都发, 
; 多个方式发送逗号隔开
send_type = 1

; 对应子任务的program, 继承[common]配置
[program:common]


;;;;;;;;;;;;;;;;;;;;;;;;;通知方式配置;;;;;;;;;;;;;;;;;;;;;;
; 云片短信通知，对应send_type:2
[yunpian]
apikey = ''
tplid = ''
; 对应短信模板的占位符
tplstr = ''

; 邮件通知，对应send_type:1
[mailer]
host = 'smtp.163.com'
username = 'xxx@163.com'
password = 'xxx'
port = 994
from = 'xxx@163.com'
name = 'Guarder'

; 微信通知，对应send_type:3
[weixin]
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
```
test.ini
```ini
; 子进程唯一的名称
program = test
; 启动子进程数量
numprocs = 1
; 待启动脚本的执行目录
directory = 
; 执行命令
command = 
; 子进程的cron表达式
cron = */2 * * * *
; 出现异常后提示次数
alarm_limit = 2
; 重复出现多少次认定为异常
retry = 3
; 子进程退出是否重启
auto_restart = 0
; log文件
logfile=
```

### 关于子进程退出状态监控
> 回收子进程后，通过以下方法监控子进程退出的状态。 
$state  = pcntl_wifexited($status);     //正常退出返回true，否则返回false  
$code   = pcntl_wexitstatus($status);   //返回进程退出的状态码  

1）正常退出：code=0，state=1。触发restart  
2）通知结束：code=1，state=1。子进程通知完毕后`exit(1)`，触发restart  
3）抛出异常：code=255，state=1。不触发restart  
4）命令退出：code=0，state=0。触发restart  


### 启动脚本
`sudo php Process.php`
然后按配置的ip和port通过web访问查看控制子进程。
