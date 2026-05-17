<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'matchers',
        'country_codes',
        'ufis',
    ];

    protected $casts = [
        'matchers' => 'array',
        'country_codes' => 'array',
        'ufis' => 'array',
    ];
}
