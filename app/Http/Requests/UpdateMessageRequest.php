<?php

namespace App\Http\Requests;

use App\Models\Message;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateMessageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('message_edit');
    }

    public function rules()
    {
        return [
            'message' => [
                'string',
                'required',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
