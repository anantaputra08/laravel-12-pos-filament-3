<?php
namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Product([
            'barcode' => $row['barcode'],
            'name' => $row['name'],
            'type_id' => $row['type_id'],
            'category_id' => $row['category_id'],
            'is_available' => $row['is_available'],
            'is_stock' => $row['is_stock'],
            'base_price' => $row['base_price'],
            'selling_price' => $row['selling_price'],
            'stock' => $row['stock'],
            'min_stock' => $row['min_stock'],
            'weight' => $row['weight'],
            'base_unit' => $row['base_unit'],
        ]);
    }
}