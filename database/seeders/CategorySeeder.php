<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Electronics' => [
                'Phones', 'Laptops', 'Tablets', 'Accessories', 'Cameras', 'Audio',
                'Televisions', 'Wearable Technology', 'Computer Components', 'Gaming',
            ],
            'Fashion' => [
                'Shoes', 'Men\'s Shoes', 'Women\'s Shoes', 'Children\'s Shoes',
                'Clothing', 'Men\'s Clothing', 'Women\'s Clothing', 'Children\'s Clothing',
                'Trousers', 'Men\'s Trousers', 'Women\'s Trousers', 'Jeans', 'Shorts',
                'Shirts & T-Shirts', 'Men\'s Shirts', 'Women\'s Tops', 'Dresses',
                'Skirts', 'Suits & Blazers', 'Men\'s Suits', 'Women\'s Suits',
                'Jackets & Coats', 'Men\'s Jackets', 'Women\'s Jackets',
                'Bags', 'Men\'s Bags', 'Women\'s Bags', 'Backpacks', 'Handbags',
                'Wallets & Purses', 'Travel Bags', 'Watches', 'Jewelry', 'Sunglasses',
                'Underwear', 'Men\'s Underwear', 'Women\'s Underwear', 'Sportswear',
                'Activewear', 'Swimwear', 'Sleepwear', 'Kids Fashion',
                'Fashion Accessories', 'Belts', 'Hats & Caps', 'Scarves', 'Gloves',
                'Socks', 'Traditional Wear', 'Costumes',
            ],
            'Home & Kitchen' => [
                'Furniture', 'Appliances', 'Cookware', 'Dining', 'Bedding', 'Decor',
                'Lighting', 'Storage & Organization', 'Bathroom', 'Garden & Outdoor',
            ],
            'Beauty' => [
                'Skincare', 'Haircare', 'Makeup', 'Fragrances', 'Bath & Body',
                'Nail Care', 'Men\'s Grooming', 'Beauty Tools', 'Personal Care',
            ],
            'Health' => [
                'Vitamins & Supplements', 'Medical Supplies', 'Fitness Equipment',
                'First Aid', 'Personal Health', 'Nutrition', 'Wellness',
            ],
            'Sports & Fitness' => [
                'Exercise Equipment', 'Sports Clothing', 'Running', 'Football',
                'Basketball', 'Cycling', 'Swimming', 'Camping & Hiking', 'Team Sports',
            ],
            'Automotive' => [
                'Car Accessories', 'Car Care', 'Motorcycle Accessories', 'Tools',
                'Replacement Parts', 'Tires & Wheels', 'Interior Accessories',
                'Exterior Accessories',
            ],
            'Books & Stationery' => [
                'Books', 'School Supplies', 'Office Supplies', 'Writing Materials',
                'Art Supplies', 'Calendars & Planners', 'Educational Materials',
            ],
            'Groceries' => [
                'Fresh Produce', 'Beverages', 'Snacks', 'Canned Foods', 'Dairy',
                'Meat & Seafood', 'Bakery', 'Breakfast Foods', 'Cooking Ingredients',
            ],
            'Baby & Kids' => [
                'Baby Clothing', 'Toys', 'Diapers', 'Feeding', 'Nursery', 'Baby Care',
                'Kids Furniture', 'Learning & Development',
            ],
            'Pet Supplies' => [
                'Dog Supplies', 'Cat Supplies', 'Bird Supplies', 'Fish Supplies',
                'Pet Food', 'Pet Grooming', 'Pet Toys', 'Pet Health',
            ],
            'Office & Business' => [
                'Office Furniture', 'Printers & Scanners', 'Computer Accessories',
                'Office Electronics', 'Packaging Materials', 'Business Equipment',
                'Cleaning Supplies',
            ],
            'Tools & Hardware' => [
                'Power Tools', 'Hand Tools', 'Electrical', 'Plumbing', 'Building Materials',
                'Safety Equipment', 'Hardware', 'Paint & Supplies',
            ],
            'Entertainment' => [
                'Video Games', 'Consoles', 'Board Games', 'Musical Instruments',
                'Movies & Music', 'Party Supplies', 'Collectibles',
            ],
            'Services' => [],
        ];

        foreach ($categories as $parentName => $children) {
            $parent = Category::firstOrCreate([
                'name' => $parentName,
            ], [
                'parent_id' => null,
            ]);

            $parent->update(['parent_id' => null]);

            foreach ($children as $childName) {
                Category::firstOrCreate([
                    'name' => $childName,
                ], [
                    'parent_id' => $parent->id,
                ])->update(['parent_id' => $parent->id]);
            }
        }
    }
}
