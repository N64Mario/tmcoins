<?php

namespace app\admin\model;

use app\common\model\TimeModel;

class ProductLists extends TimeModel
{

    protected $name = "product_lists";

    protected $deleteTime = "delete_time";

    
    public function productCate()
    {
        return $this->belongsTo('\app\admin\model\ProductCate', 'cate_id', 'id');
    }

    

}