<?php
namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Product::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Barcode',
            'Name',
            'Type ID',
            'Category ID',
            'Is Available',
            'Is Stock',
            'Base Price',
            'Selling Price',
            'Stock',
            'Min Stock',
            'Weight',
            'Base Unit',
            'Created At',
            'Updated At',
            'Deleted At',
        ];
    }
}