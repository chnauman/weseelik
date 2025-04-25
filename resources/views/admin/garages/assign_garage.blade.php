@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.assign') }} {{ trans('cruds.garage.title') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('admin.garages.assign') }}">
            @csrf
            <div class="form-group">
                <label class="required" for="garage_id">{{ trans('cruds.garage.title_singular') }}</label>
                <select class="form-control select2 {{ $errors->has('garage_id') ? 'is-invalid' : '' }}" name="garage_id" id="garage_id" required>
                    <option value="">{{ trans('global.pleaseSelect') }}</option>
                    @foreach($garages as $id => $garage)
                        <option value="{{ $id }}">{{ $garage }}</option>
                    @endforeach
                </select>
                @if($errors->has('garage_id'))
                    <div class="invalid-feedback">
                        {{ $errors->first('garage_id') }}
                    </div>
                @endif
            </div>
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.user.title_singular') }}</label>
                <select class="form-control select2 {{ $errors->has('user_id') ? 'is-invalid' : '' }}" name="user_id" id="user_id" required>
                    <option value="">{{ trans('global.pleaseSelect') }}</option>
                    @foreach($users as $id => $user)
                        <option value="{{ $id }}" {{ old('user_id', $selectedUser) == $id ? 'selected' : '' }}>{{ $user }}</option>
                    @endforeach
                </select>
                @if($errors->has('user_id'))
                    <div class="invalid-feedback">
                        {{ $errors->first('user_id') }}
                    </div>
                @endif
            </div>
            <div class="form-group">
                <button class="btn btn-danger" type="submit">
                    {{ trans('global.assign') }}
                </button>
            </div>
        </form>
    </div>
</div>

@endsection 