<?php

namespace App\Http\Controllers;

use App\Models\Product;

class WebController extends Controller
{
    public function home(){
        $query = Product::with('productImages')
            ->inRandomOrder()
            ->visible()
            ->inStock();

        if(auth()->check() && auth()->user()->categories->isNotEmpty()){
            $categories = auth()->user()->categories()->with('subCategories')->get();
            $categoryIds = [];
            foreach($categories as $category){
                array_push($categoryIds , $category->id);
                if ($category->subCategories->isNotEmpty()){
                    $subCategoryIds = $category->subCategories->pluck('id')->toArray();
                    $categoryIds = array_merge($categoryIds , $subCategoryIds);
                }
            }
            
            $heroProducts = (clone $query)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->take(3)
            ->get();
        }

        else{
            $heroProducts = (clone $query)->take(3)->get();
        }
        

        return view('home', compact('heroProducts'));
    }
}
