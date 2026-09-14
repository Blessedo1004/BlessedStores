<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class WebController extends Controller
{
    public function home(){
        $query = Product::withoutGlobalScope('user')
            ->with('productImages')
            ->inRandomOrder()
            ->visible()
            ->inStock();

        if(auth()->check() && auth()->user()->categories->isNotEmpty()){
            $categories = auth()->user()->categories()->with('subCategories')->get();
            $categoryIds = [];
            foreach($categories as $category){
                array_push($categoryIds , $category->id);
                if ($category->subCategories->isNotEmpty()){
                    $subCategoryIds = $category->subCategories->pluck('id');
                    $categoryIds = array_merge($categoryIds , $subCategoryIds);
                }
            }
            // dd($categoryIds);
            $heroProducts = (clone $query)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('id', $categoryIds);
            })
            ->take(3)
            ->get();

            $newArrivals = (clone $query)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->where('created_at', '>=', now()->startOfWeek())->take(5)->get();
        }

        else{
            $heroProducts = (clone $query)->take(3)->get();
            $newArrivals = (clone $query)
            ->where('created_at', '>=', now()->startOfWeek())
            ->take(5)
            ->get();
        }

        return view('home', compact('heroProducts', 'newArrivals'));
    }
}
