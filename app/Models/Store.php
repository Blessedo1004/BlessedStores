<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class Store extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_number', 'logo', 'slug', 'description'];
    
    public function socials(){
        return $this->hasMany(Social::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
