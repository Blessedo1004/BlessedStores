<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Store extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_number', 'logo', 'slug', 'description', 'address'];
    
    public function socials(){
        return $this->hasMany(Social::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    //create slug
    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            $store->slug = static::generateUniqueSlug($store->name);
        });

        static::updating(function (Store $store) {
            if ($store->isDirty('name')) {
                $store->slug = static::generateUniqueSlug(
                    $store->name,
                    $store->id
                );
            }
        });
    }

    protected static function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        $slug = $baseSlug;

        $count = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $count;

            $count++;
        }

        return $slug;
    }  
}
