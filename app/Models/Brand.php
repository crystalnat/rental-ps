<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'logo', 'address', 'phone', 'email', 'website', 'timezone', 'currency', 'tax_rate', 'is_active'])]
class Brand extends Model
{
    use SoftDeletes;

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->orderBy('sort_order');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function landingSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LandingSetting::class);
    }

    public function landingSections(): HasMany
    {
        return $this->hasMany(LandingSection::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'tax_rate'  => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
