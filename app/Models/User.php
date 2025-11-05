<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne, BelongsTo, BelongsToMany};

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name','email','password','role','course_id','department_id'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime'
    ];


    // Roles
    public const ROLE_REGISTRAR   = 'Registrar';
    public const ROLE_DEAN        = 'Dean';
    public const ROLE_HEAD        = 'Head';
    public const ROLE_FACULTY     = 'Faculty';

    // Basic relations
    public function course(): BelongsTo     { return $this->belongsTo(Course::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }

    // Loads
    public function administrativeLoads(): HasMany { return $this->hasMany(AdministrativeLoad::class); }
    // public function researchLoads(): HasMany       { return $this->hasMany(ResearchLoad::class); } // enable if table/model exist
    public function facultyLoads(): HasMany        { return $this->hasMany(FacultyLoad::class); }

    // Employment + dept code (legacy table name preserved)
    public function employment(): HasOne
    {
        return $this->hasOne(UsersEmployment::class, 'user_id');
    }


    public function userDepartment(): HasOne
    {
        return $this->hasOne(UsersDepartment::class, 'user_id');
    }

    // Specializations
    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(
            Specialization::class, 'user_specializations', 'user_id', 'specialization_id'
        )->withTimestamps();
    }

    // Scheduling
    public function meetingsTeaching(): HasMany { return $this->hasMany(SectionMeeting::class, 'faculty_id'); }
    public function availabilities(): HasMany   { return $this->hasMany(FacultyAvailability::class); }

    // Scopes/helpers
    public function scopeFaculty($q) { return $q->where('role', self::ROLE_FACULTY); }

    public function isAvailableOn(int $dayOfWeek, string $start, string $end): bool
    {
        return $this->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $start)
            ->where('end_time', '>=', $end)
            ->exists();
    }


    public function maxUnits(): int
    {
        // use nullsafe operator to avoid property access on null
        $regular = (int) ($this->employment?->regular_load ?? 0);
        $extra   = (int) ($this->employment?->extra_load ?? 0);

        // if you also maintain users.max_units, you can choose the policy below:
        // return max($this->max_units ?? 0, $regular + $extra);
        return $regular + $extra;
    }

    public function totalAssignedUnitsFor(int $academicId): int
    {
        // contact_hours is string in your migration; ensure casting in FacultyLoad model or cast here
        return (int) $this->facultyLoads()
            ->where('academic_id', $academicId)
            ->get()
            ->sum(fn ($fl) => (int) $fl->contact_hours);
    }

    public function remainingUnitsFor(int $academicId): int
    {
        return max(0, $this->maxUnits() - $this->totalAssignedUnitsFor($academicId));
    }

    public function initials(int $maxLetters = 2): string
    {
        $name = trim($this->name ?? '');
        if ($name === '') {
            // fallback: first letter of email or 'U'
            $first = $this->email ? mb_substr($this->email, 0, 1) : 'U';
            return mb_strtoupper($first);
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);

        // default behavior: if more than 2 parts, use first and last for a neat 2-letter badge
        if ($maxLetters === 2 && count($letters) >= 2) {
            return $letters[0] . $letters[count($letters) - 1];
        }

        return mb_substr(implode('', $letters), 0, $maxLetters);
    }

    /** Allow `$user->initials` (property style) as well. */
    public function getInitialsAttribute(): string
    {
        return $this->initials();
    }

    public function scopeInDepartment($query, ?int $deptId)
    {
        return $query->when($deptId, fn($q) => $q->where('department_id', $deptId));
    }


}
