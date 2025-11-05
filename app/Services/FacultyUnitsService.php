<?php
namespace App\Services;


use App\Models\{FacultyLoad, UsersEmployment, User};


class FacultyUnitsService
{
/** Total contact hours for a faculty within an academic term */
public function totalAssignedUnits(int $userId, int $academicId): int
{
return (int) FacultyLoad::where('user_id',$userId)
->where('academic_id',$academicId)
->sum('contact_hours');
}


/** Regular cap + allowed extra. You may enforce: use extra only after regular is full */
public function maxUnits(int $userId): int
{
$emp = UsersEmployment::where('user_id',$userId)->first();
return $emp ? $emp->maxUnits() : 0;
}


public function remainingUnits(int $userId, int $academicId): int
{
return max(0, $this->maxUnits($userId) - $this->totalAssignedUnits($userId, $academicId));
}
}