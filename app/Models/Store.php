<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Store extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_number', 'logo', 'slug', 'description'];
    
    public function socials(){
        return $this->hasMany(Social::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    //create slug
        protected static function boot()
    {
        parent::boot();
        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name);
            }
        });
    }    
}
