<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\Relations\{BelongsTo};

class FacultyLoad extends Model {

protected $fillable=['user_id','curriculum_id','contact_hours','administrative_id','section','academic_id'];

public function user(): BelongsTo { return $this->belongsTo(User::class); }

public function curriculum(): BelongsTo { return $this->belongsTo(Curriculum::class); }

public function administrative(): BelongsTo { return $this->belongsTo(AdministrativeLoad::class,'administrative_id'); }

public function academic(): BelongsTo { return $this->belongsTo(AcademicYear::class,'academic_id'); }
}
