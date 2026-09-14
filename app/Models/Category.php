<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name' , 'slug', 'parent_id'];

    public function products(){
        return $this->belongsToMany(Product::class);
    }

    public function users(){
        return $this->belongsToMany(User::class);
    }

    public function parent(){
       return $this->belongsTo(Category::class, 'parent_id');
    }
    
    public function subCategories(){
        return $this->hasMany(Category::class, 'parent_id')->with('subCategories');
    }

    public function scopeMainCategory($query){
        return $query->whereNull('parent_id');
    }

    //create slug
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            $category->slug = static::generateUniqueSlug($category->name);
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name')) {
                $category->slug = static::generateUniqueSlug(
                    $category->name,
                    $category->id
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
