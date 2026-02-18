<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot('lead_time_days', 'unit_cost')
            ->withTimestamps();
    }
}
