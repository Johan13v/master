<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'website_id',
        'author',
        'content',
        'translated_content',
        'status',
        'reference_id',
        'created_at',
        'parent_id',
        'post_id',
        'target_post_id',
        'translated_comment_id',
        'generated_response' // Add parent_id to fillable properties
    ];

    // Relationship to get child comments (replies)
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // Relationship to get the parent comment
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}

