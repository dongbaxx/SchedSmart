<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};


class UserSpecialization extends Model
{
protected $table = 'user_specializations';
protected $fillable = ['user_id','specialization_id'];
public function user(): BelongsTo { return $this->belongsTo(User::class); }
public function specialization(): BelongsTo { return $this->belongsTo(Specialization::class); }
}