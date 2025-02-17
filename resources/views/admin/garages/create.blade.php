@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.create') }} {{ trans('cruds.garage.title_singular') }}
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route("admin.garages.store") }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="required" for="user_id">{{ trans('cruds.garage.fields.user') }}</label>
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
                <span class="help-block">{{ trans('cruds.garage.fields.user_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="title">{{ trans('cruds.garage.fields.title') }}</label>
                <input class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}" type="text" name="title" id="title" value="{{ old('title', '') }}" required>
                @if($errors->has('title'))
                    <div class="invalid-feedback">
                        {{ $errors->first('title') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.title_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="description">{{ trans('cruds.garage.fields.description') }}</label>
                <input class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}" type="text" name="description" id="description" value="{{ old('description', '') }}" required>
                @if($errors->has('description'))
                    <div class="invalid-feedback">
                        {{ $errors->first('description') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.description_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="service_description">{{ trans('cruds.garage.fields.service_description') }}</label>
                <input class="form-control {{ $errors->has('service_description') ? 'is-invalid' : '' }}" type="text" name="service_description" id="service_description" value="{{ old('service_description', '') }}">
                @if($errors->has('service_description'))
                    <div class="invalid-feedback">
                        {{ $errors->first('service_description') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.service_description_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="contact_no">{{ trans('cruds.garage.fields.contact_no') }}</label>
                <input class="form-control {{ $errors->has('contact_no') ? 'is-invalid' : '' }}" type="text" name="contact_no" id="contact_no" value="{{ old('contact_no', '') }}" required>
                @if($errors->has('contact_no'))
                    <div class="invalid-feedback">
                        {{ $errors->first('contact_no') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.contact_no_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="address">{{ trans('cruds.garage.fields.address') }}</label>
                <input class="form-control {{ $errors->has('address') ? 'is-invalid' : '' }}" type="text" name="address" id="address" value="{{ old('address', '') }}">
                @if($errors->has('address'))
                    <div class="invalid-feedback">
                        {{ $errors->first('address') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.address_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="city">{{ trans('cruds.garage.fields.city') }}</label>
                <input class="form-control {{ $errors->has('city') ? 'is-invalid' : '' }}" type="text" name="city" id="city" value="{{ old('city', '') }}">
                @if($errors->has('city'))
                    <div class="invalid-feedback">
                        {{ $errors->first('city') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.city_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="country_id">{{ trans('cruds.garage.fields.country') }}</label>
                <select class="form-control select2 {{ $errors->has('country') ? 'is-invalid' : '' }}" name="country_id" id="country_id" required>
                    @foreach($countries as $id => $entry)
                        <option value="{{ $id }}" {{ old('country_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                    @endforeach
                </select>
                @if($errors->has('country'))
                    <div class="invalid-feedback">
                        {{ $errors->first('country') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.country_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="latitude">{{ trans('cruds.garage.fields.latitude') }}</label>
                <input class="form-control {{ $errors->has('latitude') ? 'is-invalid' : '' }}" type="text" name="latitude" id="latitude" value="{{ old('latitude', '') }}" required>
                @if($errors->has('latitude'))
                    <div class="invalid-feedback">
                        {{ $errors->first('latitude') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.latitude_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="longitude">{{ trans('cruds.garage.fields.longitude') }}</label>
                <input class="form-control {{ $errors->has('longitude') ? 'is-invalid' : '' }}" type="text" name="longitude" id="longitude" value="{{ old('longitude', '') }}">
                @if($errors->has('longitude'))
                    <div class="invalid-feedback">
                        {{ $errors->first('longitude') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.longitude_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="working_hours">{{ trans('cruds.garage.fields.working_hours') }}</label>
                <input class="form-control {{ $errors->has('working_hours') ? 'is-invalid' : '' }}" type="text" name="working_hours" id="working_hours" value="{{ old('working_hours', '') }}" required>
                @if($errors->has('working_hours'))
                    <div class="invalid-feedback">
                        {{ $errors->first('working_hours') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.working_hours_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="opening_time">{{ trans('cruds.garage.fields.opening_time') }}</label>
                <input class="form-control {{ $errors->has('opening_time') ? 'is-invalid' : '' }}" type="text" name="opening_time" id="opening_time" value="{{ old('opening_time', '') }}" required>
                @if($errors->has('opening_time'))
                    <div class="invalid-feedback">
                        {{ $errors->first('opening_time') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.opening_time_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="closing_time">{{ trans('cruds.garage.fields.closing_time') }}</label>
                <input class="form-control {{ $errors->has('closing_time') ? 'is-invalid' : '' }}" type="text" name="closing_time" id="closing_time" value="{{ old('closing_time', '') }}" required>
                @if($errors->has('closing_time'))
                    <div class="invalid-feedback">
                        {{ $errors->first('closing_time') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.closing_time_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required">{{ trans('cruds.garage.fields.status') }}</label>
                <select class="form-control {{ $errors->has('status') ? 'is-invalid' : '' }}" name="status" id="status" required>
                    <option value disabled {{ old('status', null) === null ? 'selected' : '' }}>{{ trans('global.pleaseSelect') }}</option>
                    @foreach(App\Models\Garage::STATUS_SELECT as $key => $label)
                        <option value="{{ $key }}" {{ old('status', 'pending') === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if($errors->has('status'))
                    <div class="invalid-feedback">
                        {{ $errors->first('status') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.status_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="logo">{{ trans('cruds.garage.fields.logo') }}</label>
                <div class="needsclick dropzone {{ $errors->has('logo') ? 'is-invalid' : '' }}" id="logo-dropzone">
                </div>
                @if($errors->has('logo'))
                    <div class="invalid-feedback">
                        {{ $errors->first('logo') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.logo_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="storefront_image">{{ trans('cruds.garage.fields.storefront_image') }}</label>
                <div class="needsclick dropzone {{ $errors->has('storefront_image') ? 'is-invalid' : '' }}" id="storefront_image-dropzone">
                </div>
                @if($errors->has('storefront_image'))
                    <div class="invalid-feedback">
                        {{ $errors->first('storefront_image') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.storefront_image_helper') }}</span>
            </div>
            <div class="form-group">
                <label for="gallery">{{ trans('cruds.garage.fields.gallery') }}</label>
                <div class="needsclick dropzone {{ $errors->has('gallery') ? 'is-invalid' : '' }}" id="gallery-dropzone">
                </div>
                @if($errors->has('gallery'))
                    <div class="invalid-feedback">
                        {{ $errors->first('gallery') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.gallery_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="tags">{{ trans('cruds.garage.fields.tag') }}</label>
                <div style="padding-bottom: 4px">
                    <span class="btn btn-info btn-xs select-all" style="border-radius: 0">{{ trans('global.select_all') }}</span>
                    <span class="btn btn-info btn-xs deselect-all" style="border-radius: 0">{{ trans('global.deselect_all') }}</span>
                </div>
                <select class="form-control select2 {{ $errors->has('tags') ? 'is-invalid' : '' }}" name="tags[]" id="tags" multiple required>
                    @foreach($tags as $id => $tag)
                        <option value="{{ $id }}" {{ in_array($id, old('tags', [])) ? 'selected' : '' }}>{{ $tag }}</option>
                    @endforeach
                </select>
                @if($errors->has('tags'))
                    <div class="invalid-feedback">
                        {{ $errors->first('tags') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.tag_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="languages">{{ trans('cruds.garage.fields.language') }}</label>
                <div style="padding-bottom: 4px">
                    <span class="btn btn-info btn-xs select-all" style="border-radius: 0">{{ trans('global.select_all') }}</span>
                    <span class="btn btn-info btn-xs deselect-all" style="border-radius: 0">{{ trans('global.deselect_all') }}</span>
                </div>
                <select class="form-control select2 {{ $errors->has('languages') ? 'is-invalid' : '' }}" name="languages[]" id="languages" multiple required>
                    @foreach($languages as $id => $language)
                        <option value="{{ $id }}" {{ in_array($id, old('languages', [])) ? 'selected' : '' }}>{{ $language }}</option>
                    @endforeach
                </select>
                @if($errors->has('languages'))
                    <div class="invalid-feedback">
                        {{ $errors->first('languages') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.language_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="specializations">{{ trans('cruds.garage.fields.specialization') }}</label>
                <div style="padding-bottom: 4px">
                    <span class="btn btn-info btn-xs select-all" style="border-radius: 0">{{ trans('global.select_all') }}</span>
                    <span class="btn btn-info btn-xs deselect-all" style="border-radius: 0">{{ trans('global.deselect_all') }}</span>
                </div>
                <select class="form-control select2 {{ $errors->has('specializations') ? 'is-invalid' : '' }}" name="specializations[]" id="specializations" multiple required>
                    @foreach($specializations as $id => $specialization)
                        <option value="{{ $id }}" {{ in_array($id, old('specializations', [])) ? 'selected' : '' }}>{{ $specialization }}</option>
                    @endforeach
                </select>
                @if($errors->has('specializations'))
                    <div class="invalid-feedback">
                        {{ $errors->first('specializations') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.specialization_helper') }}</span>
            </div>
            <div class="form-group">
                <label class="required" for="services">{{ trans('cruds.garage.fields.services') }}</label>
                <div style="padding-bottom: 4px">
                    <span class="btn btn-info btn-xs select-all" style="border-radius: 0">{{ trans('global.select_all') }}</span>
                    <span class="btn btn-info btn-xs deselect-all" style="border-radius: 0">{{ trans('global.deselect_all') }}</span>
                </div>
                <select class="form-control select2 {{ $errors->has('services') ? 'is-invalid' : '' }}" name="services[]" id="services" multiple required>
                    @foreach($services as $id => $service)
                        <option value="{{ $id }}" {{ in_array($id, old('services', [])) ? 'selected' : '' }}>{{ $service }}</option>
                    @endforeach
                </select>
                @if($errors->has('services'))
                    <div class="invalid-feedback">
                        {{ $errors->first('services') }}
                    </div>
                @endif
                <span class="help-block">{{ trans('cruds.garage.fields.services_helper') }}</span>
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

@section('scripts')
<script>
    Dropzone.options.logoDropzone = {
    url: '{{ route('admin.garages.storeMedia') }}',
    maxFilesize: 2, // MB
    acceptedFiles: '.jpeg,.jpg,.png,.gif',
    maxFiles: 1,
    addRemoveLinks: true,
    headers: {
      'X-CSRF-TOKEN': "{{ csrf_token() }}"
    },
    params: {
      size: 2,
      width: 4096,
      height: 4096
    },
    success: function (file, response) {
      $('form').find('input[name="logo"]').remove()
      $('form').append('<input type="hidden" name="logo" value="' + response.name + '">')
    },
    removedfile: function (file) {
      file.previewElement.remove()
      if (file.status !== 'error') {
        $('form').find('input[name="logo"]').remove()
        this.options.maxFiles = this.options.maxFiles + 1
      }
    },
    init: function () {
@if(isset($garage) && $garage->logo)
      var file = {!! json_encode($garage->logo) !!}
          this.options.addedfile.call(this, file)
      this.options.thumbnail.call(this, file, file.preview ?? file.preview_url)
      file.previewElement.classList.add('dz-complete')
      $('form').append('<input type="hidden" name="logo" value="' + file.file_name + '">')
      this.options.maxFiles = this.options.maxFiles - 1
@endif
    },
    error: function (file, response) {
        if ($.type(response) === 'string') {
            var message = response //dropzone sends it's own error messages in string
        } else {
            var message = response.errors.file
        }
        file.previewElement.classList.add('dz-error')
        _ref = file.previewElement.querySelectorAll('[data-dz-errormessage]')
        _results = []
        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            node = _ref[_i]
            _results.push(node.textContent = message)
        }

        return _results
    }
}

</script>
<script>
    Dropzone.options.storefrontImageDropzone = {
    url: '{{ route('admin.garages.storeMedia') }}',
    maxFilesize: 2, // MB
    acceptedFiles: '.jpeg,.jpg,.png,.gif',
    maxFiles: 1,
    addRemoveLinks: true,
    headers: {
      'X-CSRF-TOKEN': "{{ csrf_token() }}"
    },
    params: {
      size: 2,
      width: 4096,
      height: 4096
    },
    success: function (file, response) {
      $('form').find('input[name="storefront_image"]').remove()
      $('form').append('<input type="hidden" name="storefront_image" value="' + response.name + '">')
    },
    removedfile: function (file) {
      file.previewElement.remove()
      if (file.status !== 'error') {
        $('form').find('input[name="storefront_image"]').remove()
        this.options.maxFiles = this.options.maxFiles + 1
      }
    },
    init: function () {
@if(isset($garage) && $garage->storefront_image)
      var file = {!! json_encode($garage->storefront_image) !!}
          this.options.addedfile.call(this, file)
      this.options.thumbnail.call(this, file, file.preview ?? file.preview_url)
      file.previewElement.classList.add('dz-complete')
      $('form').append('<input type="hidden" name="storefront_image" value="' + file.file_name + '">')
      this.options.maxFiles = this.options.maxFiles - 1
@endif
    },
    error: function (file, response) {
        if ($.type(response) === 'string') {
            var message = response //dropzone sends it's own error messages in string
        } else {
            var message = response.errors.file
        }
        file.previewElement.classList.add('dz-error')
        _ref = file.previewElement.querySelectorAll('[data-dz-errormessage]')
        _results = []
        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            node = _ref[_i]
            _results.push(node.textContent = message)
        }

        return _results
    }
}

</script>
<script>
    var uploadedGalleryMap = {}
Dropzone.options.galleryDropzone = {
    url: '{{ route('admin.garages.storeMedia') }}',
    maxFilesize: 2, // MB
    acceptedFiles: '.jpeg,.jpg,.png,.gif',
    addRemoveLinks: true,
    headers: {
      'X-CSRF-TOKEN': "{{ csrf_token() }}"
    },
    params: {
      size: 2,
      width: 4096,
      height: 4096
    },
    success: function (file, response) {
      $('form').append('<input type="hidden" name="gallery[]" value="' + response.name + '">')
      uploadedGalleryMap[file.name] = response.name
    },
    removedfile: function (file) {
      console.log(file)
      file.previewElement.remove()
      var name = ''
      if (typeof file.file_name !== 'undefined') {
        name = file.file_name
      } else {
        name = uploadedGalleryMap[file.name]
      }
      $('form').find('input[name="gallery[]"][value="' + name + '"]').remove()
    },
    init: function () {
@if(isset($garage) && $garage->gallery)
      var files = {!! json_encode($garage->gallery) !!}
          for (var i in files) {
          var file = files[i]
          this.options.addedfile.call(this, file)
          this.options.thumbnail.call(this, file, file.preview ?? file.preview_url)
          file.previewElement.classList.add('dz-complete')
          $('form').append('<input type="hidden" name="gallery[]" value="' + file.file_name + '">')
        }
@endif
    },
     error: function (file, response) {
         if ($.type(response) === 'string') {
             var message = response //dropzone sends it's own error messages in string
         } else {
             var message = response.errors.file
         }
         file.previewElement.classList.add('dz-error')
         _ref = file.previewElement.querySelectorAll('[data-dz-errormessage]')
         _results = []
         for (_i = 0, _len = _ref.length; _i < _len; _i++) {
             node = _ref[_i]
             _results.push(node.textContent = message)
         }

         return _results
     }
}

</script>
@endsection