<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TransactionItem;

class Transaction extends Model
{   
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'total_price',
    ];

    protected $casts = [
        'total_price' => 'integer',
    ];
    
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
