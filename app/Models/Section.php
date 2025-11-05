<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};


class Section extends Model
{
protected $fillable = ['section_name','year_level','course_id'];
public function course(): BelongsTo { return $this->belongsTo(Course::class); }
public function offerings(): HasMany { return $this->hasMany(CourseOffering::class); }
}