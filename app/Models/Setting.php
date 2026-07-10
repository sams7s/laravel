<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'slot_duration',
        'start_time',
        'end_time',
        'off_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'off_days' => 'array',
    ];

    /**
     * Get the off days as an array
     *
     * @return array
     */
    public function getOffDaysAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return array_map('intval', explode(',', $value));
        }

        return [];
    }
}
