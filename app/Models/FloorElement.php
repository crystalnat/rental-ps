<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['floor_id', 'type', 'name', 'x_meters', 'y_meters', 'width_meters', 'length_meters', 'rotation_deg', 'meta', 'sort_order'])]
class FloorElement extends Model
{
    public const TYPE_PILLAR = 'pillar';
    public const TYPE_STAIRS = 'stairs';
    public const TYPE_CASHIER = 'cashier';
    public const TYPE_WALL = 'wall';
    public const TYPE_DOOR = 'door';
    public const TYPE_COUNTER = 'counter';
    public const TYPE_OTHER = 'other';

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PILLAR  => 'Tiang',
            self::TYPE_STAIRS  => 'Tangga',
            self::TYPE_CASHIER => 'Kasir',
            self::TYPE_WALL    => 'Dinding',
            self::TYPE_DOOR    => 'Pintu',
            self::TYPE_COUNTER => 'Counter',
            self::TYPE_OTHER   => 'Lainnya',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    protected function casts(): array
    {
        return [
            'x_meters'     => 'float',
            'y_meters'     => 'float',
            'width_meters' => 'float',
            'length_meters'=> 'float',
            'rotation_deg' => 'integer',
            'meta'         => 'array',
            'sort_order'   => 'integer',
        ];
    }
}
