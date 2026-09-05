<?php

namespace Botble\RealEstate\Models;

use Botble\Base\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOpenHouse extends BaseModel
{
    protected $table = 're_property_open_houses';

    protected $fillable = [
        'property_id',
        'listing_key',
        'open_house_key',
        'open_house_date',
        'start_time',
        'end_time',
        'time_range',
        'format',
        'type',
        'status',
        'url',
    ];

    protected $casts = [
        'open_house_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Formatted date matching standard open house display, e.g. "Saturday, September 5th".
     */
    protected function formattedDate(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->open_house_date) {
                return '';
            }

            return Carbon::parse($this->open_house_date)->isoFormat('dddd, MMMM Do');
        });
    }

    /**
     * Formatted time range matching screenshot, e.g. "2-4 pm" or "1:30-4 pm".
     */
    protected function formattedTime(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->time_range) {
                return $this->time_range;
            }

            if (! $this->start_time || ! $this->end_time) {
                return '';
            }

            // TRREB times are in UTC; convert to America/Toronto
            $start = Carbon::parse($this->start_time)->timezone('America/Toronto');
            $end = Carbon::parse($this->end_time)->timezone('America/Toronto');

            $startFmt = $start->minute === 0 ? $start->format('g') : $start->format('g:i');
            $endFmt = $end->minute === 0 ? $end->format('g a') : $end->format('g:i a');

            return sprintf('%s-%s', $startFmt, $endFmt);
        });
    }

    public function isUpcoming(): bool
    {
        if (! $this->open_house_date) {
            return false;
        }

        $today = Carbon::now('America/Toronto')->startOfDay();
        return Carbon::parse($this->open_house_date)->startOfDay()->gte($today);
    }
}
