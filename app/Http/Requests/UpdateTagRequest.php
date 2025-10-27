<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateTagRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('tag_edit');
    }

    public function rules()
    {
        return [
            'title' => [
                'string',
                'required',
                'unique:tags,title,' . request()->route('tag')->id,
            ],
            'english_title' => [
                'nullable',
                'string',
            ],
        ];
    }
}
