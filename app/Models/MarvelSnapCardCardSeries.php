<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarvelSnapCardCardSeries extends Model
{
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->table = 'marvel_snap_card_card_series';
    }
}
