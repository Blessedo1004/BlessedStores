<?php

use Livewire\Component;
use App\Models\Category;


new class extends Component
{
    public array $categoryIds = [];
    public $userCategories;

    public function mount(){
        if(auth()->check() && auth()->user()->categories->isNotEmpty()){
            foreach (auth()->user()->categories as $category){
                if(!$category->parent_id){
                    $this->categoryIds[] = $category->id;
                }
                else if($category->parent_id && !in_array($category->parent_id, $this->categoryIds)){
                    $this->categoryIds[] = $category->parent_id;    
                }
                $categoryIdCount = count($this->categoryIds);
                if( $categoryIdCount < 4){
                  $extraCategoryIds = Category::mainCategory()->inRandomOrder()->take(4 - $categoryIdCount )->pluck('id')->toArray(); 
                  $this->categoryIds = array_merge($this->categoryIds , $extraCategoryIds);
                }
            }
            $this->userCategories = Category::whereIn('id' , $this->categoryIds)->inRandomOrder()->take(4)->get(['id' , 'name']);
        }

        else{
            $this->userCategories = Category::mainCategory()->inRandomOrder()->take(4)->get(['id' , 'name']);
        }
    }
};
?>

<div class="main-menu">
    <nav id="mobile-menu">
        <ul>
            @foreach ($userCategories as $category)
                <li class="has-dropdown">
                    <a href="{{ route('category-products', $category->id) }}" wire:navigate>{{ $category->name }}</a>
                    <ul class="submenu">
                        @foreach ($category->subCategories->sortBy('name') as $subCategory)
                             <li><a href="{{ route('category-products', $subCategory->id) }}" wire:navigate>{{ $subCategory->name }}</a></li>   
                        @endforeach
                    </ul>
                </li>    
            @endforeach   
        </ul>
    </nav>
</div>