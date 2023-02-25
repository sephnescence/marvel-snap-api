<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * @property Uuid $id
 * @property Uuid $marvel_snap_card_id
 * @property string $name
 * @property Carbon $lifespan_start
 * @property Carbon $lifespan_end
 * @property array $snapfan_data
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
        ];
        $this->fillable = [
            'lifespan_end',
            'lifespan_start',
            'marvel_snap_card_id',
            'name',
            'snapfan_data',
        ];
        $this->incrementing = false;
        $this->table = 'marvel_snap_card_variants';
    }
}
