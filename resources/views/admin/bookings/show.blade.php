@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.booking.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.bookings.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.id') }}
                        </th>
                        <td>
                            {{ $booking->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.reference_no') }}
                        </th>
                        <td>
                            {{ $booking->reference_no }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.user') }}
                        </th>
                        <td>
                            {{ $booking->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.garage') }}
                        </th>
                        <td>
                            {{ $booking->garage->title ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.appointment_time') }}
                        </th>
                        <td>
                            {{ $booking->appointment_time }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.status') }}
                        </th>
                        <td>
                            {{ App\Models\Booking::STATUS_SELECT[$booking->status] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.processing_status') }}
                        </th>
                        <td>
                            {{ App\Models\Booking::PROCESSING_STATUS_SELECT[$booking->processing_status] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.garage_remarks') }}
                        </th>
                        <td>
                            {{ $booking->garage_remarks }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.client_remarks') }}
                        </th>
                        <td>
                            {{ $booking->client_remarks }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.booking.fields.client_rating') }}
                        </th>
                        <td>
                            {{ $booking->client_rating }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.bookings.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection
