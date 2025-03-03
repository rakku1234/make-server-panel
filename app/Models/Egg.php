<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Egg extends Model
{
    use Sluggable;

    protected $table = 'eggs';

    protected $primaryKey = 'egg_id';

    protected $fillable = [
        'egg_id',
        'uuid',
        'name',
        'description',
        'docker_images',
        'egg_url',
        'egg_variables',
        'public',
    ];

    protected $casts = [
        'docker_images' => 'array',
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
