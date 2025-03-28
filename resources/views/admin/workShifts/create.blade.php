@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.workShift.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.work-shifts.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="opening_time">{{ trans('cruds.workShift.fields.opening_time') }}</label>
                <input class="form-control timepicker {{ $errors->has('opening_time') ? 'is-invalid' : '' }}" type="text" name="opening_time" id="opening_time" value="{{ old('opening_time') }}" required>
                @if($errors->has('opening_time'))
                    <div class="invalid-feedback">
                        {{ $errors->first('opening_time') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.workShift.fields.opening_time_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="closing_time">{{ trans('cruds.workShift.fields.closing_time') }}</label>
                <input class="form-control timepicker {{ $errors->has('closing_time') ? 'is-invalid' : '' }}" type="text" name="closing_time" id="closing_time" value="{{ old('closing_time') }}" required>
                @if($errors->has('closing_time'))
                    <div class="invalid-feedback">
                        {{ $errors->first('closing_time') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.workShift.fields.closing_time_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="day_of_week">{{ trans('cruds.workShift.fields.day_of_week') }}</label>
                <input class="form-control {{ $errors->has('day_of_week') ? 'is-invalid' : '' }}" type="text" name="day_of_week" id="day_of_week" value="{{ old('day_of_week', '') }}">
                @if($errors->has('day_of_week'))
                    <div class="invalid-feedback">
                        {{ $errors->first('day_of_week') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.workShift.fields.day_of_week_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="garage_id">{{ trans('cruds.workShift.fields.garage') }}</label>
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
                <span class="help-block">{{ trans('cruds.workShift.fields.garage_helper') }}</span>
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