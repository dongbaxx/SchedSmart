<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};


class UsersDepartment extends Model
{
protected $table = 'users_departments';

protected $fillable = [
                       'user_code_id',
                       'position',
                       'department_id',
                       'user_id'];
public function user(): BelongsTo { return $this->belongsTo(User::class); }
public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}
