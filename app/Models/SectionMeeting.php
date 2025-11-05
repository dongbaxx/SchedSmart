<?php

// app/Models/SectionMeeting.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SectionMeeting extends Model {
    protected $fillable = [
        'offering_id','curriculum_id','faculty_id','room_id',
        'day','start_time','end_time','type','notes'
    ];

    public function offering(){ return $this->belongsTo(CourseOffering::class); }
    public function curriculum(){ return $this->belongsTo(Curriculum::class); }
    public function room(){ return $this->belongsTo(Room::class); }
    public function faculty(){ return $this->belongsTo(User::class, 'faculty_id'); }
}
