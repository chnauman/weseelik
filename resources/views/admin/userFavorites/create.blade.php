@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.userFavorite.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.user-favorites.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.userFavorite.fields.user') }}</label>
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
                <span class="help-block">{{ trans('cruds.userFavorite.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="garage_id">{{ trans('cruds.userFavorite.fields.garage') }}</label>
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
                <span class="help-block">{{ trans('cruds.userFavorite.fields.garage_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="note">{{ trans('cruds.userFavorite.fields.note') }}</label>
                <input class="form-control {{ $errors->has('note') ? 'is-invalid' : '' }}" type="text" name="note" id="note" value="{{ old('note', '') }}">
                @if($errors->has('note'))
                    <div class="invalid-feedback">
                        {{ $errors->first('note') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.userFavorite.fields.note_helper') }}</span>
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