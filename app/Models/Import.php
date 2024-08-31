<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'revenue_stream_id',
        'title',
    ];

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function revenueStream()
    {
        return $this->belongsTo(RevenueStream::class);
    }
}
