<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\FilterByUser;

class Cart extends Model
{
    use FilterByUser;
    protected $fillable = ['user_id', 'product_id', 'quantity'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
