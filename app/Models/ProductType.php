<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    /**
     * Relasi ke produk (One-to-Many)
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'type_id');
    }

    /**
     * Scope untuk mencari tipe produk berdasarkan nama
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where('name', 'like', '%' . $keyword . '%');
    }
}
