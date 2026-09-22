<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFloorPlan extends BaseModel
{
    protected $table = 're_project_floor_plans';

    protected $fillable = [
        'project_id',
        'external_id',
        'floorplan_uuid',
        'name',
        'bedrooms',
        'bathrooms',
        'size',
        'exposure',
        'availability',
        'current_price',
        'current_psf',
        'launch_price',
        'floor_min',
        'floor_max',
        'image_full',
        'image_medium',
        'image_thumbnail',
        'local_image',
        'price_history',
    ];

    protected $casts = [
        'size' => 'float',
        'current_price' => 'float',
        'current_psf' => 'float',
        'launch_price' => 'float',
        'floor_min' => 'int',
        'floor_max' => 'int',
        'price_history' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
