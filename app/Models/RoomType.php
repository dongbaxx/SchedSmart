<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $fillable = ['name','description'];

    public function rooms(): HasMany { return $this->hasMany(Room::class); }
}
