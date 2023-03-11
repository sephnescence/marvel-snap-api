<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Ramsey\Uuid\Uuid;

/**
 * @property Uuid $id
 * @property array $artists
 * @property array $downloads
 * @property Uuid $marvel_snap_card_id
 * @property string $name
 * @property Carbon $lifespan_start
 * @property Carbon $lifespan_end
 * @property array $snapfan_data
 * @property array $internal_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class MarvelSnapCardVariant extends Model
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
            'internal_data',
            'lifespan_end',
            'lifespan_start',
            'marvel_snap_card_id',
            'name',
            'snapfan_data',
        ];
        $this->incrementing = false;
        $this->table = 'marvel_snap_card_variants';
    }

    public function getDownloadsAttribute(): array
    {
        return [
            'backgrounds' => array_map(
                function ($background) {
                    return Vite::asset('resources/images/variants/' . $this->name . '/' . $background);
                },
                $this->internal_data['downloads']['backgrounds'] ?? []
            ),
            'foregrounds' => array_map(
                function ($foreground) {
                    return Vite::asset('resources/images/variants/' . $this->name . '/' . $foreground);
                },
                $this->internal_data['downloads']['foregrounds'] ?? []
            ),
        ];
    }

    public function getArtistsAttribute(): array
    {
        $artistName = 'unknown';
        $colouristName = 'unknown';
        foreach ($this->snapfan_data['current']['data']['artists'] ?? [] as $artist) {
            if ($artist['artistType'] === 'Artist') {
                $artistName = $artist['name'];
            }
            if ($artist['artistType'] === 'Colorist') {
                $colouristName = $artist['name'];
            }
        }
        return [
            'artist' => $artistName,
            'colourist' => $colouristName,
        ];
    }
}
