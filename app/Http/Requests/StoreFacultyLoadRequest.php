<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacultyLoadRequest extends FormRequest
{
public function authorize(): bool { return $this->user()!=null; }

public function rules(): array {
return [
'user_id' => ['required','exists:users,id'],
'curriculum_id' => ['required','exists:curricula,id'],
'contact_hours' => ['required','integer','min:0'],
'section' => ['required','string','max:255'],
'academic_id' => ['required','exists:academic_years,id'],
'administrative_id' => ['nullable','exists:administrative_loads,id'],
'research_load_id' => ['nullable','exists:research_loads,id'],
];
}
}
