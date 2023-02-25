<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'lifespan_end',
            'lifespan_start',
            'name',
            'snapfan_data',
            'internal_data',
        ];
        $this->table = 'marvel_snap_cards';
    }
}
