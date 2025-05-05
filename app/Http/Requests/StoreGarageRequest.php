<?php

namespace App\Http\Requests;

use App\Models\Garage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreGarageRequest extends FormRequest
{

    public function authorize()
    {
        //return Gate::allows('garage_create');
        return true;
    }


    public function rules()
    {
        return [
            /*
            'user_id' => [
                'required',
                'integer',
            ],
            */
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
            'status' => [
                'required',
            ],
            'home_service' => [
                'boolean',
                'nullable',
            ],
            'pickup_service' => [
                'boolean',
                'nullable',
            ],
            /*
            'logo' => [
                'required',
            ],
            'storefront_image' => [
                'required',
            ],
            'gallery' => [
                'array',
            ],
            */
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
            'governate' => [
                'string',
                'nullable',
            ],
            'area' => [
                'string',
                'nullable',
            ],
            'block' => [
                'string',
                'nullable',
            ],
            'maps_share_link' => [
                'string',
                'nullable',
            ],
            'services.*' => [
                'integer',
            ],
            'services' => [
                'required',
                'array',
            ],
            /*
            'categories.*' => [
                'integer',
            ],
            'categories' => [
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
            */
            'registered_by' => [
                'required',
            ],
            'work_shifts' => [
                'array',
            ],
            'work_shifts.*.opening_time' => [
                'required',
                'string',
            ],
            'work_shifts.*.closing_time' => [
                'required',
                'string',
            ],
            'work_shifts.*.day_of_week' => [
                'nullable',
                'string',
            ],
            'contacts' => [
                'array',
            ],
            'contacts.*.contact_no' => [
                'required',
                'string',
            ],
            'contacts.*.type' => [
                'nullable',
                'string',
                'in:mobile,landline,fax',
            ],
            'contacts.*.status' => [
                'nullable',
                'string',
                'in:active,deactive',
            ],
        ];
    }
}
