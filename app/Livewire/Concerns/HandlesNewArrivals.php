<?php

namespace App\Livewire\Concerns;

use App\Models\Product;

trait HandlesNewArrivals
{
    public function loadNewArrivals()
    {
        $query = Product::with(
            'productImages',
            'brand',
            'categories',
            'productVariants.size'
        )
        ->inRandomOrder()
        ->visible()
        ->inStock();

        if (auth()->check() && auth()->user()->categories->isNotEmpty()) {
            $categories = auth()->user()
                ->categories()
                ->with('subCategories')
                ->get();

            $categoryIds = [];

            foreach ($categories as $category) {
                $categoryIds[] = $category->id;

                if ($category->subCategories->isNotEmpty()) {
                    $categoryIds = array_merge(
                        $categoryIds,
                        $category->subCategories->pluck('id')->toArray()
                    );
                }
            }

            $this->totalNewArrivals = (clone $query)
                ->whereHas('categories', function ($query) use ($categoryIds) {
                    $query->whereIn('category_id', $categoryIds);
                })
                ->where('created_at', '>=', now()->startOfWeek())
                ->get();

            $this->newArrivals = (clone $query)
                ->whereHas('categories', function ($query) use ($categoryIds) {
                    $query->whereIn('category_id', $categoryIds);
                })
                ->where('created_at', '>=', now()->startOfWeek())
                ->take(5)
                ->get();
        } else {
            $this->totalNewArrivals = (clone $query)
                ->where('created_at', '>=', now()->startOfMonth())
                ->get();

            $this->newArrivals = (clone $query)
                ->where('created_at', '>=', now()->startOfMonth())
                ->take(5)
                ->get();
        }
    }
}