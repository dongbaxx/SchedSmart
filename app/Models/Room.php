<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Room extends Model
{
    protected $fillable = ['building_id','room_type_id','code','capacity','is_active'];

    public function building(): BelongsTo { return $this->belongsTo(Building::class); }
    public function type(): BelongsTo { return $this->belongsTo(RoomType::class, 'room_type_id'); }
    public function meetings(): HasMany { return $this->hasMany(SectionMeeting::class); }
}
