<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'address',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * @return array<string, string|null>
     */
    public function contact(): array
    {
        $settings = (array) ($this->settings ?? []);

        return [
            'phone' => isset($settings['contact_phone']) ? (string) $settings['contact_phone'] : null,
            'email' => isset($settings['contact_email']) ? (string) $settings['contact_email'] : null,
            'city' => isset($settings['city']) ? (string) $settings['city'] : null,
            'lat' => isset($settings['lat']) ? (string) $settings['lat'] : null,
            'lng' => isset($settings['lng']) ? (string) $settings['lng'] : null,
            'maps_url' => isset($settings['maps_url']) ? (string) $settings['maps_url'] : null,
        ];
    }
}
