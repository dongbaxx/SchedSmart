<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;


class AdministrativeLoad extends Model {
    
protected $fillable=['load_desc','units','user_id'];
public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
