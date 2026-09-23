<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['name', 'quantity', 'price', 'weight', 'sku' , 'product_id' , 'size_id'];

    public function size(){
        return $this->belongsTo(Size::class);
    }

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
