<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends Model
{
    protected $fillable = [
        'academic_id','course_id','section_id','year_level','effectivity_year',
        'status','approved_by','approved_at',
    ];

    // Primary relation (use this name in blades if you prefer)
    public function academic(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_id');
    }

    // Alias para compatible sa uban blades/components
    public function academicYear(): BelongsTo
    {
        return $this->academic();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    // For your scheduling editor (SectionMeeting model)
    public function meetings(): HasMany
    {
        return $this->hasMany(SectionMeeting::class, 'offering_id');
    }
}
