<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;
    protected $fillable = ['name' , 'slug'];

    public function products(){
        return $this->hasMany(Product::class);
    }

    //create slug
    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            $brand->slug = static::generateUniqueSlug($brand->name);
        });

        static::updating(function (Brand $brand) {
            if ($brand->isDirty('name')) {
                $brand->slug = static::generateUniqueSlug(
                    $brand->name,
                    $brand->id
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
