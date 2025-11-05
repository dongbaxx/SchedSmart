<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Building extends Model
{
    protected $fillable = ['code','name','department_id'];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function rooms(): HasMany { return $this->hasMany(Room::class); }
}
