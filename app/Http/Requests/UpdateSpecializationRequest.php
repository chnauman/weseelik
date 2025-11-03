<?php

namespace App\Http\Requests;

use App\Models\Specialization;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateSpecializationRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('specialization_edit');
    }

    public function rules()
    {
        return [
            'title' => [
                'string',
                'required',
                'unique:specializations,title,' . request()->route('specialization')->id,
            ],
            'english_title' => [
                'nullable',
                'string',
            ],
        ];
    }
}
