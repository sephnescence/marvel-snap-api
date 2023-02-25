<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Uuid $id
 * @property string $name
 * @property Carbon $lifespan_start
 * @property Carbon $lifespan_end
 * @property array $snapfan_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property ?MarvelSnapCardSeries $currentCardSeries
 * @property ?MarvelSnapCardCardSeries $currentCardCardSeries
 */
class MarvelSnapCard extends Model
{
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->casts = [
            'lifespan_end' => 'date',
            'lifespan_start' => 'date',
            'snapfan_data' => 'array',
            'internal_data' => 'array',
        ];
        $this->fillable = [
            'internal_data', // BTTODO - Move to a new table
            'lifespan_end',
            'lifespan_start',
            'name',
            'snapfan_data',
        ];
        $this->incrementing = false;
        $this->table = 'marvel_snap_cards';
    }

    // BTTODO - Will pick this up later after finding some good documentation on a many to many relationship
    //  I'll define my own way for now, but it's possibly not best practice. Not sure how much need there
    //  even is for this to be a relationship though to be honest
    public function getCurrentCardSeriesAttribute(): ?MarvelSnapCardSeries
    {
        return null;
    }

    public function getCurrentCardCardSeriesAttribute(): ?MarvelSnapCardCardSeries
    {
        return MarvelSnapCardCardSeries::where('marvel_snap_card_id', '=', $this->attributes['id'])
            ->orderBy('lifespan_start', 'asc')
            ->first();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(
            MarvelSnapCardVariant::class,
            'marvel_snap_card_id',
            'id'
        );
    }
}
