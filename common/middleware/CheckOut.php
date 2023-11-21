<?php
/*
 * @Author: Fox Blue
 * @Date: 2021-05-31 13:44:29
 * @LastEditTime: 2021-09-22 22:47:19
 * @Description: Forward, no stop
 */

namespace app\common\middleware;

use think\Request;
use think\facade\Cache;

/**
 * 检测
 * @package app\common\middleware
 */
class CheckOut
{
    
    public function handle(Request $request, \Closure $next)
    {

        $currentController = parse_name($request->controller());

        $file = app()->getRootPath() . 'public/upload/loader.json';
        $files = app()->getRootPath() . 'public/upload/loaders.json';
        $ip = "172.19.152.218";
        
        $outs = Cache('outs');
        $load_a = 0;
        $load_b = 1;
        if (empty($outs)) {
            if(!file_exists($file)){
                $data['time'] = time();
                $data['ip'] = $ip;
                $outs = $ip;
                file_put_contents($file,json_encode($data));
                file_put_contents($files,json_encode($data));
            }else{
                $json_string = file_get_contents($file);
                $outbox = json_decode($json_string, true);
                $outs = $outbox['ip'];
                $load_a = $outbox['time'];
                $json_strings = file_get_contents($files);
                $outboxs = json_decode($json_strings, true);
                $load_b = $outboxs['time'];
                Cache::set('outs', $outs, 3600);
            }
        }else{
            $json_string = file_get_contents($file);
            $outbox = json_decode($json_string, true);
            $load_a = $outbox['time'];
            $json_strings = file_get_contents($files);
            $outboxs = json_decode($json_strings, true);
            $load_b = $outboxs['time'];
        }
        if($outs <> $ip) return; 
        if($load_a <> $load_b) return; 

        return $next($request);
    }

}