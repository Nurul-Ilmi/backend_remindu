<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (isset($attributes['key']) && (str_contains($attributes['key'], 'token') || str_contains($attributes['key'], 'key'))) {
                    try {
                        return Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        return $value;
                    }
                }
                return $value;
            },
            set: function ($value, $attributes) {
                if (isset($attributes['key']) && (str_contains($attributes['key'], 'token') || str_contains($attributes['key'], 'key'))) {
                    return Crypt::encryptString($value);
                }
                return $value;
            }
        );
    }
}
