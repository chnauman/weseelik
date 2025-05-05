@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.garage.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.garages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.id') }}
                        </th>
                        <td>
                            {{ $garage->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.user') }}
                        </th>
                        <td>
                            {{ $garage->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.title') }}
                        </th>
                        <td>
                            {{ $garage->title }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.description') }}
                        </th>
                        <td>
                            {{ $garage->description }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.service_description') }}
                        </th>
                        <td>
                            {{ $garage->service_description }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.contact_no') }}
                        </th>
                        <td>
                            {{ $garage->contact_no }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.address') }}
                        </th>
                        <td>
                            {{ $garage->address }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.city') }}
                        </th>
                        <td>
                            {{ $garage->city }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.country') }}
                        </th>
                        <td>
                            {{ $garage->country->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.latitude') }}
                        </th>
                        <td>
                            {{ $garage->latitude }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.longitude') }}
                        </th>
                        <td>
                            {{ $garage->longitude }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.status') }}
                        </th>
                        <td>
                            {{ App\Models\Garage::STATUS_SELECT[$garage->status] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.logo') }}
                        </th>
                        <td>
                            @if($garage->getMedia('logo')->count() > 0)
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    @foreach($garage->getMedia('logo') as $logo)
                                        <a href="{{ $logo->getUrl() }}" target="_blank" style="display: inline-block">
                                            <img src="{{ $logo->getUrl('thumb') }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.storefront_image') }}
                        </th>
                        <td>
                            @if($garage->getMedia('storefront_image')->count() > 0)
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    @foreach($garage->getMedia('storefront_image') as $image)
                                        <a href="{{ $image->getUrl() }}" target="_blank" style="display: inline-block">
                                            <img src="{{ $image->getUrl('thumb') }}" style="max-width: 200px; max-height: 200px; object-fit: contain;">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.gallery') }}
                        </th>
                        <td>
                            @foreach($garage->gallery as $key => $media)
                                <a href="{{ $media->getUrl() }}" target="_blank" style="display: inline-block">
                                    <img src="{{ $media->getUrl('thumb') }}">
                                </a>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.tag') }}
                        </th>
                        <td>
                            @foreach($garage->tags as $key => $tag)
                                <span class="label label-info">{{ $tag->title }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.language') }}
                        </th>
                        <td>
                            @foreach($garage->languages as $key => $language)
                                <span class="label label-info">{{ $language->title }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.specialization') }}
                        </th>
                        <td>
                            @foreach($garage->specializations as $key => $specialization)
                                <span class="label label-info">{{ $specialization->title }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.services') }}
                        </th>
                        <td>
                            @foreach($garage->services as $key => $services)
                                <span class="label label-info">{{ $services->title }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.category') }}
                        </th>
                        <td>
                            @foreach($garage->categories as $key => $category)
                                <span class="label label-info">{{ $category->title }}</span>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.rating') }}
                        </th>
                        <td>
                            {{ $garage->rating }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.garage.fields.registered_by') }}
                        </th>
                        <td>
                            {{ App\Models\Garage::REGISTERED_BY_SELECT[$garage->registered_by] ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.garages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection