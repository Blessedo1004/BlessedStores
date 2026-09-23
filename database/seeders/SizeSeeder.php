<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Size;

class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl' , 'xxxl'];

        for($s = 0; $s < count($sizes) ; $s++){
            Size::create(["name" => $sizes[$s]]);
        }
    }
}
