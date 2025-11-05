<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};


class Curriculum extends Model
{
protected $table = 'curricula';
protected $fillable = [
        'course_code',
        'descriptive_title',
        'units',
        'lec',
        'lab',
        'cmo',
        'hei',
        'pre_requisite',
        'course_id',
        'year_level',
        'specialization_id',
        'efectivity_year',
        'semester'
];
public function course(): BelongsTo { return $this->belongsTo(Course::class); }
public function specialization(): BelongsTo { return $this->belongsTo(Specialization::class); }
public function facultyLoads(): HasMany { return $this->hasMany(FacultyLoad::class); }
}