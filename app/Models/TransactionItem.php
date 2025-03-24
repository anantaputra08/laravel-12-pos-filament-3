<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_unit_id',
        'product_price',
        'qty',
        'total_price',
        'timestamp'
    ];

    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

}
