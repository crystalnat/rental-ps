<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSection extends Model
{
    // Bit Mask Flags
    public const FLAG_VISIBLE      = 1 << 0; // 1
    public const FLAG_FULL_WIDTH   = 1 << 1; // 2
    public const FLAG_DARK_BG      = 1 << 2; // 4
    public const FLAG_REVERSE_GRID = 1 << 3; // 8

    protected $fillable = [
        'brand_id',
        'section_key',
        'title',
        'subtitle',
        'content',
        'items',
        'config',
        'image',
        'sort_order',
        'section_bitmask',
    ];

    protected function casts(): array
    {
        return [
            'items'           => 'array',
            'config'          => 'array',
            'section_bitmask' => 'integer',
        ];
    }

    public function isVisible(): bool
    {
        return ($this->section_bitmask & self::FLAG_VISIBLE) === self::FLAG_VISIBLE;
    }

    public function setFlag(int $flag, bool $value): void
    {
        if ($value) {
            $this->section_bitmask |= $flag;
        } else {
            $this->section_bitmask &= ~$flag;
        }
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
