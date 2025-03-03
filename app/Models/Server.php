<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Server extends Model
{
    use Sluggable;
    protected $table = 'servers';

    protected $fillable = [
        'limits',
        'user',
        'egg',
        'feature_limits',
        'status',
        'suspended',
        'uuid',
        'name',
        'description',
        'external_id',
        'allocation_id',
        'start_on_completion',
        'docker_image',
        'egg_variables',
        'slug',
        'node',
    ];

    protected $casts = [
        'limits'         => 'array',
        'feature_limits' => 'array',
        'suspended'      => 'boolean',
        'egg_variables'  => 'array',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
