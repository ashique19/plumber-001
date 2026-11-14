<?php

use App\Casts\BusinessSettingsPayload;
use App\Models\BusinessSetting;
use Illuminate\Database\Eloquent\Model;

it('decodes double encoded json settings strings like some mariadb imports', function () {
    $cast = new BusinessSettingsPayload;
    $model = new class extends Model
    {
        protected $table = 'business_settings';
    };

    $inner = json_encode(['default_locale' => 'bn', 'theme' => ['layout_key' => 'plumber']], JSON_UNESCAPED_UNICODE);
    $outer = json_encode($inner, JSON_UNESCAPED_UNICODE);

    $decoded = $cast->get($model, 'settings', $outer, []);

    expect($decoded)->toBeArray()
        ->and($decoded['default_locale'])->toBe('bn');
});
