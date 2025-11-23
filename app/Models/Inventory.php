<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'batch_number',
        'quantity',
        'expiry_date',
        'is_archived'
    ];

    protected $casts = [
        'expiry_date' => 'date', // ensures Laravel converts it to a Carbon instance
    ];


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements()
    {
        return $this->hasMany(ProductMovement::class);
    }

    public function getBranchNameAttribute()
    {
        return $this->branch?->name ?? 'Unknown Branch';
    }
}