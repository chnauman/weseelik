<?php

namespace App\Http\Requests;

use App\Models\UserFavorite;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreUserFavoriteRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('user_favorite_create');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'garage_id' => [
                'required',
                'integer',
            ],
            'note' => [
                'string',
                'nullable',
            ],
        ];
    }
}
