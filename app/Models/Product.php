<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    protected $fillable = ['store_id','user_id', 'name', 'quantity', 'price', 'slug', 'description', 'weight', 'sku', 'brand_id'];

    public function store(){
        return $this->belongsTo(Store::class);
    }
    
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function productImages(){
        return $this->hasMany(ProductImage::class);
    }

    public function categories(){
        return $this->belongsToMany(Category::class);
    }

    public function brand(){
        return $this->belongsTo(Brand::class);
    }

    public function reviews(){
        return $this->hasMany(ProductReview::class);
    }

    public function carts(){
        return $this->hasMany(Cart::class);
    }

    public function wishlists(){
        return $this->hasMany(Wishlist::class);
    }

    public function productVariants(){
        return $this->hasMany(ProductVariant::class);
    }
    
    //create slug
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            $product->slug = static::generateUniqueSlug($product->name);
        });

        static::creating(function (Product $product) {
            if (Auth::check()) {
                $product->user_id = Auth::id();
            }
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

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', 'published');
    }

}

