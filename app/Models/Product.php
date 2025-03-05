<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'barcode',
        'name',
        'type_id',
        'category_id',
        'is_available',
        'is_stock',
        'base_price',
        'selling_price',
        'stock',
        'min_stock',
        'weight',
        'base_unit',
    ];

    /**
     * Relasi ke ProductType
     */
    public function type()
    {
        return $this->belongsTo(ProductType::class, 'type_id');
    }

    /**
     * Relasi ke Category
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Relasi ke ProductUnit
     */
    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    /**
     * Scope untuk mencari produk berdasarkan nama atau barcode
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where('name', 'like', "%{$keyword}%")
            ->orWhere('barcode', 'like', "%{$keyword}%");
    }
}
