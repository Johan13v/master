<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'amount',
        'city_id',
        'revenue_stream_id',
        'website_id',
        'import_id',
        'order_date',
        'status',
        'customer_language',
        'reference_id'
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function revenueStream()
    {
        return $this->belongsTo(RevenueStream::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    public function import()
    {
        return $this->belongsTo(Import::class);
    }
}
