<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $table = 'academic_years';
    protected $fillable = ['school_year', 'semester', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
    public static function current(): ?self
    {
        return static::where('is_active', 1)->first();
    }


    public function offerings(): HasMany { return $this->hasMany(CourseOffering::class, 'academic_id'); }
    public function facultyLoads(): HasMany { return $this->hasMany(FacultyLoad::class, 'academic_id'); }
}
