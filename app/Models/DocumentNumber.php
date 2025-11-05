<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DocumentNumber extends Model {

    
protected $fillable=['document_number','effective_date','revision_number','for'];
protected $casts=['effective_date'=>'date'];
}
