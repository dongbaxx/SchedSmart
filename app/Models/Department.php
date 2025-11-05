<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Department extends Model
{
use HasFactory;
protected $fillable = ['department_name','department_description'];
public function courses(): HasMany { return $this->hasMany(Course::class); }
public function users(): HasMany { return $this->hasMany(User::class); }

public function buildings() { return $this->hasMany(Building::class); }

}