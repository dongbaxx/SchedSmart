<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyAvailability extends Model
{
    protected $fillable = [
        'user_id', 'day', 'time_slot_id', 'is_available', 'is_preferred'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
