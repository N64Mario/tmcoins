<?php

namespace app\admin\model;

use app\common\model\TimeModel;

class KongPriceCron extends TimeModel
{

    protected $name = "kong_price_cron";
    protected $type = [
       // "execute_time"=>"timestamp"
    ];

    public function product()
    {
        return $this->belongsTo('\app\admin\model\ProductLists', 'kong_id', 'id');
    }

    

}