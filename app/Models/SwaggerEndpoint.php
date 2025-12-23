<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwaggerEndpoint extends Model
{
    use HasFactory;
    protected $fillable = [
        'swagger_source_id', 'method', 'path', 'summary', 'description', 'request_body', 'response_body',
    ];
    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
    ];
    public function source()
    {
        return $this->belongsTo(SwaggerSource::class, 'swagger_source_id');
    }
    public function fields()
    {
        return $this->hasMany(SwaggerField::class, 'endpoint_id');
    }
}
