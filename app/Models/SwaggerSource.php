<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwaggerSource extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'url', 'meta',
    ];
    protected $casts = [
        'meta' => 'array',
    ];
    public function endpoints()
    {
        return $this->hasMany(SwaggerEndpoint::class);
    }
}
