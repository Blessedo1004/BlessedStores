<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoreApplication extends Model
{
    protected $fillable = ['owner_name', 'store_name',  'email','phone_number', 'logo', 'slug', 'description', 'address', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function applicationSocials()
    {
        return $this->hasMany(ApplicationSocial::class);
    }

    //create slug
    protected static function booted(): void
    {
        static::creating(function (StoreApplication $store) {
            $store->slug = static::generateUniqueSlug($store->store_name);
        });

        static::updating(function (StoreApplication $store) {
            if ($store->isDirty('store_name')) {
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
