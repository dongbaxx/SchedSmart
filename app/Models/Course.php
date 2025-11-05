<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};


class Course extends Model
{
protected $fillable = ['course_name','course_description','department_id'];
public function department(): BelongsTo { return $this->belongsTo(Department::class); }
public function sections(): HasMany { return $this->hasMany(Section::class); }
public function curricula(): HasMany { return $this->hasMany(Curriculum::class); }
public function offerings(): HasMany { return $this->hasMany(CourseOffering::class); }
public function specializations(): HasMany { return $this->hasMany(Specialization::class); }
}