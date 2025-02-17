<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateBookingRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('booking_edit');
    }

    public function rules()
    {
        return [
            'reference_no' => [
                'string',
                'required',
                'unique:bookings,reference_no,' . request()->route('booking')->id,
            ],
            'user_id' => [
                'required',
                'integer',
            ],
            'garage_id' => [
                'required',
                'integer',
            ],
            'appointment_time' => [
                'required',
                'date_format:' . config('panel.date_format') . ' ' . config('panel.time_format'),
            ],
            'garage_remarks' => [
                'string',
                'nullable',
            ],
            'client_remarks' => [
                'string',
                'nullable',
            ],
            'client_rating' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
        ];
    }
}
