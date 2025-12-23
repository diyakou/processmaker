<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwaggerField extends Model
{
    use HasFactory;
    protected $fillable = [
        'endpoint_id', 'name', 'type', 'required', 'description', 'example',
    ];
    public function endpoint()
    {
        return $this->belongsTo(SwaggerEndpoint::class, 'endpoint_id');
    }
}
