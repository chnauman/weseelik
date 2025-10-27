@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.contact.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.contacts.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="garage_id">{{ trans('cruds.contact.fields.garage') }}</label>
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
                <span class="help-block">{{ trans('cruds.contact.fields.garage_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="contact_no">{{ trans('cruds.contact.fields.contact_no') }}</label>
                <input class="form-control {{ $errors->has('contact_no') ? 'is-invalid' : '' }}" type="text" name="contact_no" id="contact_no" value="{{ old('contact_no', '') }}" required>
                @if($errors->has('contact_no'))
                    <div class="invalid-feedback">
                        {{ $errors->first('contact_no') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.contact.fields.contact_no_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required">{{ trans('cruds.contact.fields.status') }}</label>
                <select class="form-control {{ $errors->has('status') ? 'is-invalid' : '' }}" name="status" id="status" required>
                    <option value disabled {{ old('status', null) === null ? 'selected' : '' }}>{{ trans('global.pleaseSelect') }}</option>
                    @foreach(App\Models\Contact::STATUS_SELECT as $key => $label)
                        <option value="{{ $key }}" {{ old('status', 'active') === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if($errors->has('status'))
                    <div class="invalid-feedback">
                        {{ $errors->first('status') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.contact.fields.status_helper') }}</span>
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