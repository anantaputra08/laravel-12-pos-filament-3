<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'gross_amount',
        'paid_amount',
        'change_amount',
        'status',
        'fraud_status',
        'payment_type',
        'issuer',
        'acquirer',
        'payment_code',
        'va_number',
        'expiry_time',
        'timestamp'
    ];

    public static function generateOrderId()
    {
        $today = now()->format('Ymd');
        $countToday = DB::table('transactions')
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return $today . str_pad($countToday, 5, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }
}
