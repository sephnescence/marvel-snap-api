<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarvelSnapCardSeries extends Model
{
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->casts = [
            'lifespan_end' => 'date',
            'lifespan_start' => 'date',
        ];
        $this->fillable = [
            'lifespan_end',
            'lifespan_start',
            'name',
        ];
        $this->table = 'marvel_snap_card_series';
    }
}
