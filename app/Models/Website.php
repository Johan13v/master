<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'website_address',
        'matchers',
        'username',
        'application_password',
    ];

    protected $casts = [
        'matchers' => 'array',
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
