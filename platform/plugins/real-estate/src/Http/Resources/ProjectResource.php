<?php

namespace Botble\RealEstate\Http\Resources;

use Botble\Media\Facades\RvMedia;
use Botble\Shortcode\Facades\Shortcode;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'content' => Shortcode::compile((string) $this->content, true)->toHtml(),
            'location' => $this->location,
            'images' => collect($this->images)->map(function ($image) {
                return RvMedia::getImageUrl($image);
            })->toArray(),
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'featured_priority' => $this->featured_priority,
            'number_block' => $this->number_block,
            'number_floor' => $this->number_floor,
            'number_flat' => $this->number_flat,
            'date_finish' => $this->date_finish?->toDateString(),
            'date_sell' => $this->date_sell?->toDateString(),
            'price_from' => $this->price_from,
            'price_from_formatted' => $this->price_from_formatted,
            'price_to' => $this->price_to,
            'price_to_formatted' => $this->price_to_formatted,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', function () {
                return $this->currency ? [
                    'id' => $this->currency->id,
                    'title' => $this->currency->title,
                    'symbol' => $this->currency->symbol,
                    'is_prefix_symbol' => $this->currency->is_prefix_symbol,
                    'decimals' => $this->currency->decimals,
                    'exchange_rate' => $this->currency->exchange_rate,
                    'is_default' => $this->currency->is_default,
                ] : null;
            }),
            'city_id' => $this->city_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'city' => $this->whenLoaded('city', function () {
                return [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                ];
            }),
            'state' => $this->whenLoaded('state', function () {
                return [
                    'id' => $this->state->id,
                    'name' => $this->state->name,
                ];
            }),
            'country' => $this->whenLoaded('country', function () {
                return [
                    'id' => $this->country->id,
                    'name' => $this->country->name,
                ];
            }),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zip_code' => $this->zip_code,
            'unique_id' => $this->unique_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'url' => $this->url,
            'slug' => $this->slug,
            'suites_starting_floor' => $this->suites_starting_floor,
            'number_of_suites_per_floor' => $this->number_of_suites_per_floor,
            'suite_size_from' => $this->suite_size_from,
            'suite_size_to' => $this->suite_size_to,
            'price_per_sqft_from' => $this->price_per_sqft_from,
            'parking_price' => $this->parking_price,
            'locker_price' => $this->locker_price,
            'total_min_deposit' => $this->total_min_deposit,
            'deposit_notes' => $this->deposit_notes,
            'development_levies' => $this->development_levies,
            'assignment_policy' => $this->assignment_policy,
            'est_maint' => $this->est_maint,
            'locker_maint' => $this->locker_maint,
            'parking_maint' => $this->parking_maint,
            'est_property_tax' => $this->est_property_tax,
            'maintenance_notes' => $this->maintenance_notes,
            'neighbour' => $this->neighbour,
            'intersection' => $this->intersection,
            'architects' => $this->architects,

            // Relationships
            'investor' => $this->whenLoaded('investor', function () {
                return new InvestorResource($this->investor);
            }),
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'author' => $this->whenLoaded('author', function () {
                return new AccountResource($this->author);
            }),
            'properties' => $this->whenLoaded('properties', function () {
                return ListPropertyResource::collection($this->properties);
            }),
            'properties_count' => $this->whenLoaded('properties', function () {
                return $this->properties->count();
            }),
        ];
    }
}
