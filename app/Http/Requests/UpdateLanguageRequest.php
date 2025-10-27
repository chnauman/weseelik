<?php

namespace App\Http\Requests;

use App\Models\Language;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateLanguageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('language_edit');
    }

    public function rules()
    {
        return [
            'title' => [
                'string',
                'required',
                'unique:languages,title,' . request()->route('language')->id,
            ],
        ];
    }
}
