<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class UsersEmployment extends Model
{
protected $table = 'users_employments';
protected $fillable = [
'employment_classification','employment_status','regular_load','extra_load','user_id'
];
public function user(): BelongsTo { return $this->belongsTo(User::class); }


// Max assignable units = regular_load + extra_load (policy may limit extra usage)
public function maxUnits(): int { return (int)($this->regular_load ?? 0) + (int)($this->extra_load ?? 0); }
}
