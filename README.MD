## Guarder

### smarty

### 子进程释放父进程的上下文资源

### 关于子进程退出状态监控
> 回收子进程后，通过以下方法监控子进程退出的状态。
$state  = pcntl_wifexited($status);     //正常退出返回true，否则返回false
$code   = pcntl_wexitstatus($status);   //返回进程退出的状态码

1）正常退出：code=0，state=1。
2）通知结束：code=1，state=1。在子进程通知完毕之后，则退出子进程，并且`exit(1)`。
3）抛出异常：code=255，state=1。
4）命令退出：code=0，state=0。



### 关于状态转换

对比下supervisor的状态枚举：
```
STOPPED = 0
STARTING = 10
RUNNING = 20
BACKOFF = 30
STOPPING = 40
EXITED = 100
FATAL = 200
UNKNOWN = 1000
```