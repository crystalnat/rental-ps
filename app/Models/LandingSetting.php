<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

    // Bit Mask Flags
    public const FLAG_PUBLISHED           = 1 << 0; // 1
    public const FLAG_SHOW_HEADER_LOGO    = 1 << 1; // 2
    public const FLAG_SHOW_FOOTER_SOCIAL  = 1 << 2; // 4
    public const FLAG_STICKY_HEADER       = 1 << 3; // 8

    protected $fillable = [
        'brand_id',
        'site_title',
        'tagline',
        'logo',
        'favicon',
        'colors',
        'fonts',
        'social_links',
        'seo',
        'custom_domain',
        'settings_bitmask',
    ];

    protected function casts(): array
    {
        return [
            'colors'           => 'array',
            'fonts'            => 'array',
            'social_links'     => 'array',
            'seo'              => 'array',
            'settings_bitmask' => 'integer',
        ];
    }

    public function isPublished(): bool
    {
        return ($this->settings_bitmask & self::FLAG_PUBLISHED) === self::FLAG_PUBLISHED;
    }

    public function hasFlag(int $flag): bool
    {
        return ($this->settings_bitmask & $flag) === $flag;
    }

    public function setFlag(int $flag, bool $value): void
    {
        if ($value) {
            $this->settings_bitmask |= $flag;
        } else {
            $this->settings_bitmask &= ~$flag;
        }
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
