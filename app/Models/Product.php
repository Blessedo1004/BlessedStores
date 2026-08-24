<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\FilterByUser;

class Product extends Model
{
    use FilterByUser;
    protected $fillable = ['store_id','user_id', 'name', 'quantity', 'price', 'slug', 'description', 'status'];

    public function store(){
        return $this->belongsTo(Store::class);
    }
    
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function productImages(){
        return $this->hasMany(ProductImage::class);
    }

    //create slug
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug = static::generateUniqueSlug($product->name);
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('name')) {
                $product->slug = static::generateUniqueSlug(
                    $product->name,
                    $product->id
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
