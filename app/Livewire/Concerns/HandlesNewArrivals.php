<?php

namespace App\Livewire\Concerns;

use App\Models\Product;

trait HandlesNewArrivals
{
    public function loadNewArrivals(?int $perPage = null, string $sortBy = 'latest')
    {
        $query = Product::with(
            'productImages',
            'brand',
            'categories',
            'productVariants.size',
            'store:id,slug,name'
        )
        ->visible()
        ->inStock()
        ->where('created_at', '>=', now()->startOfMonth());

        $categoryIds = [];

        if (auth()->check() && auth()->user()->categories->isNotEmpty()) {
            $categories = auth()->user()
                ->categories()
                ->with('subCategories')
                ->get();

            foreach ($categories as $category) {
                $categoryIds[] = $category->id;

                if ($category->subCategories->isNotEmpty()) {
                    $categoryIds = array_merge(
                        $categoryIds,
                        $category->subCategories->pluck('id')->toArray()
                    );
                }
            }

            $query->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            });
        }

        if ($perPage !== null) {
            match ($sortBy) {
                'price_low' => $query->orderBy('price'),
                'price_high' => $query->orderByDesc('price'),
                'name' => $query->orderBy('name'),
                default => $query->latest(),
            };

            return $query->paginate($perPage);
        }

        $this->totalNewArrivals = (clone $query)->inRandomOrder()->get();
        $this->newArrivals = (clone $query)
                ->inRandomOrder()
                ->take(5)
                ->get();
    }
}