<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            // Electronics
            'Apple', 'Samsung', 'Google', 'OnePlus', 'Xiaomi', 'Huawei', 'Nokia',
            'Tecno', 'Infinix', 'Itel', 'Oppo', 'Vivo', 'Sony', 'LG', 'Motorola',
            'Dell', 'HP', 'Lenovo', 'Asus', 'Acer', 'Microsoft', 'Razer', 'MSI',
            'Canon', 'Nikon', 'Fujifilm', 'GoPro', 'JBL', 'Bose', 'Beats',
            'Sennheiser', 'Anker', 'Philips', 'Hisense', 'TCL', 'Nintendo',
            'PlayStation', 'Xbox', 'Logitech', 'Kingston', 'SanDisk',

            // Fashion
            'Nike', 'Adidas', 'Puma', 'New Balance', 'Reebok', 'Under Armour',
            'Vans', 'Converse', 'Skechers', 'Timberland', 'Clarks', 'Crocs',
            'Gucci', 'Fendi', 'Prada', 'Louis Vuitton', 'Chanel', 'Dior',
            'Burberry', 'Versace', 'Balenciaga', 'Valentino', 'Armani', 'Lacoste',
            'Ralph Lauren', 'Tommy Hilfiger', 'Calvin Klein', 'Hugo Boss',
            'Zara', 'H&M', 'Uniqlo', 'Levi\'s', 'Diesel', 'Supreme',
            'Michael Kors', 'Coach', 'Kate Spade', 'Tory Burch', 'Guess',
            'Ray-Ban', 'Oakley', 'Rolex', 'Casio', 'Fossil', 'Swatch', 'Seiko',
            'Timex', 'Pandora', 'Swarovski',

            // Nigerian fashion and beauty
            'Lisa Folawiyo', 'Kenneth Ize', 'Deji & Kola', 'Maki Oh', 'Orange Culture',
            'Mai Atafo', 'Eki Orleans', 'Andrea Iyamah', 'Tiffany Amber', 'Zaron',
            'House of Tara', 'Nuban Beauty', 'BM Pro', 'R&R Luxury', 'Arami Essentials',
            'Oriki', 'VSP Botanics', 'Natural Nigerian', 'Glam Corps',

            // Home & Kitchen
            'IKEA', 'Ashley Furniture', 'West Elm', 'Samsung Home', 'LG Home',
            'Bosch', 'Whirlpool', 'Miele', 'Kenwood', 'Ninja', 'Vitamix',
            'De\'Longhi', 'Russell Hobbs', 'Tefal', 'KitchenAid', 'Pyrex',
            'Philips Home', 'Dyson', 'Shark', 'Karcher', 'Hisense Home',

            // Beauty and personal care
            'L\'Oreal', 'Maybelline', 'MAC', 'NARS', 'Revlon', 'Estée Lauder',
            'Clinique', 'Neutrogena', 'CeraVe', 'The Ordinary', 'La Roche-Posay',
            'Dove', 'Nivea', 'Vaseline', 'Olay', 'Garnier', 'Kiehl\'s',
            'Fenty Beauty', 'Rare Beauty', 'Huda Beauty', 'Yves Saint Laurent',
            'Calvin Klein Beauty', 'Chanel Beauty', 'Hugo Boss Fragrances',

            // Sports and fitness
            'Wilson', 'Spalding', 'Rawlings', 'Easton', 'Everlast', 'Titleist',
            'Callaway', 'Under Armour Fitness', 'Peloton', 'Fitbit', 'Garmin',
            'Decathlon', 'The North Face', 'Columbia', 'Patagonia', 'Salomon',
            'Hydro Flask', 'Stanley', 'Bowflex', 'Nike Training',

            // Automotive and tools
            'Toyota', 'Honda', 'Ford', 'Mercedes-Benz', 'BMW', 'Audi', 'Lexus',
            'Volkswagen', 'Nissan', 'Hyundai', 'Kia', 'Bosch Automotive',
            'Castrol', 'Mobil', 'Shell', 'Michelin', 'Bridgestone', 'Goodyear',
            'Makita', 'DeWalt', 'Stanley Tools', 'Black+Decker', 'Dremel',

            // Books, office, baby, grocery, and pet supplies
            'Moleskine', 'Pilot', 'Parker', 'BIC', 'Staedtler', 'Faber-Castell',
            'Crayola', 'HP Office', 'Brother', 'Epson', 'Pampers', 'Huggies',
            'Johnson\'s Baby', 'Gerber', 'LEGO', 'Mattel', 'Hasbro', 'Hot Wheels',
            'Nestle', 'Coca-Cola', 'Pepsi', 'Cadbury', 'Kellogg\'s', 'Lipton',
            'Purina', 'Pedigree', 'Whiskas', 'Royal Canin', 'Hill\'s Pet Nutrition',

            // Nigerian food, household, retail, automotive, and service brands
            'Dangote', 'Golden Penny', 'Honeywell Flour', 'Nasco', 'Mouka', 'Peak Milk',
            'Dano', 'Chi Limited', 'Chivita', 'Nigerian Breweries',
            'Seven-Up Bottling Company', 'Fan Milk', 'Indomie Nigeria', 'Gino',
            'Power Oil', 'Mamador', 'Onga', 'Minimie', 'Tom Tom', 'Eva Water',
            'Jumia', 'Konga', 'Slot', '3C Hub', 'Game Nigeria',
            'Innoson Vehicle Manufacturing', 'Nord Motion', 'Jet Systems Motors',
            'Air Peace', 'GIG Mobility', 'MTN Nigeria', 'Airtel Nigeria', 'Globacom',
        ];

        foreach ($brands as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
