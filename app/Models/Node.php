<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Node extends Model
{
    use Sluggable;

    protected $fillable = [
        'node_id',
        'uuid',
        'name',
        'slug',
        'description',
        'public',
        'maintenance_mode',
        'created_at',
        'updated_at',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => ['source' => 'name'],
        ];
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
