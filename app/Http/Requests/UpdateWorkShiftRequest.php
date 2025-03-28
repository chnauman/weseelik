<?php

namespace App\Http\Requests;

use App\Models\WorkShift;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateWorkShiftRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('work_shift_edit');
    }

    public function rules()
    {
        return [
            'opening_time' => [
                'required',
                'date_format:' . config('panel.time_format'),
            ],
            'closing_time' => [
                'required',
                'date_format:' . config('panel.time_format'),
            ],
            'day_of_week' => [
                'string',
                'nullable',
            ],
            'garage_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
