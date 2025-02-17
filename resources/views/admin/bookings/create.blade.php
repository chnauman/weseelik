@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.booking.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.bookings.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="reference_no">{{ trans('cruds.booking.fields.reference_no') }}</label>
                <input class="form-control {{ $errors->has('reference_no') ? 'is-invalid' : '' }}" type="text" name="reference_no" id="reference_no" value="{{ old('reference_no', '') }}" required>
                @if($errors->has('reference_no'))
                    <div class="invalid-feedback">
                        {{ $errors->first('reference_no') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.reference_no_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.booking.fields.user') }}</label>
                <select class="form-control select2 {{ $errors->has('user') ? 'is-invalid' : '' }}" name="user_id" id="user_id" required>
                    @foreach($users as $id => $entry)
                        <option value="{{ $id }}" {{ old('user_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('user'))
                    <div class="invalid-feedback">
                        {{ $errors->first('user') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="garage_id">{{ trans('cruds.booking.fields.garage') }}</label>
                <select class="form-control select2 {{ $errors->has('garage') ? 'is-invalid' : '' }}" name="garage_id" id="garage_id" required>
                    @foreach($garages as $id => $entry)
                        <option value="{{ $id }}" {{ old('garage_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('garage'))
                    <div class="invalid-feedback">
                        {{ $errors->first('garage') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.garage_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="appointment_time">{{ trans('cruds.booking.fields.appointment_time') }}</label>
                <input class="form-control datetime {{ $errors->has('appointment_time') ? 'is-invalid' : '' }}" type="text" name="appointment_time" id="appointment_time" value="{{ old('appointment_time') }}" required>
                @if($errors->has('appointment_time'))
                    <div class="invalid-feedback">
                        {{ $errors->first('appointment_time') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.appointment_time_helper') }}</span>
            </div>
            <div class="form-group">
                <label>{{ trans('cruds.booking.fields.status') }}</label>
                <select class="form-control {{ $errors->has('status') ? 'is-invalid' : '' }}" name="status" id="status">
                    <option value disabled {{ old('status', null) === null ? 'selected' : '' }}>{{ trans('global.pleaseSelect') }}</option>
                    @foreach(App\Models\Booking::STATUS_SELECT as $key => $label)
                        <option value="{{ $key }}" {{ old('status', 'pending') === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if($errors->has('status'))
                    <div class="invalid-feedback">
                        {{ $errors->first('status') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.status_helper') }}</span>
            </div>
            <div class="form-group">
                <label>{{ trans('cruds.booking.fields.processing_status') }}</label>
                <select class="form-control {{ $errors->has('processing_status') ? 'is-invalid' : '' }}" name="processing_status" id="processing_status">
                    <option value disabled {{ old('processing_status', null) === null ? 'selected' : '' }}>{{ trans('global.pleaseSelect') }}</option>
                    @foreach(App\Models\Booking::PROCESSING_STATUS_SELECT as $key => $label)
                        <option value="{{ $key }}" {{ old('processing_status', 'n/a') === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if($errors->has('processing_status'))
                    <div class="invalid-feedback">
                        {{ $errors->first('processing_status') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.processing_status_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="garage_remarks">{{ trans('cruds.booking.fields.garage_remarks') }}</label>
                <input class="form-control {{ $errors->has('garage_remarks') ? 'is-invalid' : '' }}" type="text" name="garage_remarks" id="garage_remarks" value="{{ old('garage_remarks', '') }}">
                @if($errors->has('garage_remarks'))
                    <div class="invalid-feedback">
                        {{ $errors->first('garage_remarks') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.garage_remarks_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="client_remarks">{{ trans('cruds.booking.fields.client_remarks') }}</label>
                <input class="form-control {{ $errors->has('client_remarks') ? 'is-invalid' : '' }}" type="text" name="client_remarks" id="client_remarks" value="{{ old('client_remarks', '') }}">
                @if($errors->has('client_remarks'))
                    <div class="invalid-feedback">
                        {{ $errors->first('client_remarks') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.client_remarks_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="client_rating">{{ trans('cruds.booking.fields.client_rating') }}</label>
                <input class="form-control {{ $errors->has('client_rating') ? 'is-invalid' : '' }}" type="number" name="client_rating" id="client_rating" value="{{ old('client_rating', '0') }}" step="1">
                @if($errors->has('client_rating'))
                    <div class="invalid-feedback">
                        {{ $errors->first('client_rating') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.booking.fields.client_rating_helper') }}</span>
            </div>
            <div class="form-group">
                <button class="btn btn-danger" type="submit">
                    {{ trans('global.save') }}
                </button>
            </div>
        </form>
    </div>
</div>



@endsection