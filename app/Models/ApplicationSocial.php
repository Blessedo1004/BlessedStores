<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSocial extends Model
{
    protected $fillable = ['store_application_id', 'platform', 'user_name'];

    public function storeApplication()
    {
        return $this->belongsTo(StoreApplication::class);
    }
}
