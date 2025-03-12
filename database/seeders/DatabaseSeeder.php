<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductType;
use App\Models\User;
use Carbon\Carbon;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('1234'),
        ]);

        $productTypes = [
            ['name' => 'Default'],
            ['name' => 'Multi Satuan'],
        ];

        foreach ($productTypes as $type) {
            ProductType::create($type);
        }

        $categories = [
            ['name' => 'Drinks'],
            ['name' => 'Foods'],
            ['name' => 'Snacks'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
