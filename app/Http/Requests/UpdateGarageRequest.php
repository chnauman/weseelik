<?php

namespace App\Http\Requests;

use App\Models\Garage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateGarageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('garage_edit');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'title' => [
                'string',
                'required',
            ],
            'description' => [
                'string',
                'required',
            ],
            'service_description' => [
                'string',
                'nullable',
            ],
            'contact_no' => [
                'string',
                'required',
            ],
            'address' => [
                'string',
                'nullable',
            ],
            'city' => [
                'string',
                'nullable',
            ],
            'country_id' => [
                'required',
                'integer',
            ],
            'latitude' => [
                'string',
                'required',
            ],
            'longitude' => [
                'string',
                'nullable',
            ],
            'working_hours' => [
                'string',
                'required',
            ],
            'opening_time' => [
                'string',
                'required',
            ],
            'closing_time' => [
                'string',
                'required',
            ],
            'status' => [
                'required',
            ],
            'logo' => [
                'required',
            ],
            'storefront_image' => [
                'required',
            ],
            'gallery' => [
                'array',
            ],
            'tags.*' => [
                'integer',
            ],
            'tags' => [
                'required',
                'array',
            ],
            'languages.*' => [
                'integer',
            ],
            'languages' => [
                'required',
                'array',
            ],
            'specializations.*' => [
                'integer',
            ],
            'specializations' => [
                'required',
                'array',
            ],
            'services.*' => [
                'integer',
            ],
            'services' => [
                'required',
                'array',
            ],
        ];
    }
}
