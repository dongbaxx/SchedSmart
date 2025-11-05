<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    protected $fillable = ['start_time','end_time','is_active'];
    protected $casts = ['start_time'=>'datetime:H:i', 'end_time'=>'datetime:H:i'];

    public function meetings(): HasMany { return $this->hasMany(SectionMeeting::class); }
}
