<?php

namespace pzr\guarder;

use Closure;

interface ITask 
{
    /**
     * 执行命令路口
     *
     * @param Closure $callback
     * @return void
     */
    public function run(Closure $callback);
   
}
