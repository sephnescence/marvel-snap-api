<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property Uuid $id
 * @property string $name
 * @property Carbon $lifespan_start
 * @property Carbon $lifespan_end
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
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
        $this->incrementing = false;
        $this->table = 'marvel_snap_card_series';
    }
}
