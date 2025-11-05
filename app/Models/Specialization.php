<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};


class Specialization extends Model
{
protected $fillable = ['name','course_id'];
public function course(): BelongsTo { return $this->belongsTo(Course::class); }
public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'user_specializations'); }
public function curricula(): HasMany { return $this->hasMany(Curriculum::class); }
}