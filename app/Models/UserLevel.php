<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    protected $fillable = ['name']; // Para magamit sa Seeder

    public function users()
    {
        return $this->hasMany(User::class);
    }
}