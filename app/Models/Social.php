<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Social extends Model
{
    protected $fillable = ['store_id', 'type', 'user_name'];
    public function store(){
        return $this->belongsTo(Store::class);
    }
}
