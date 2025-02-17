@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.userFavorite.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.user-favorites.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.userFavorite.fields.id') }}
                        </th>
                        <td>
                            {{ $userFavorite->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.userFavorite.fields.user') }}
                        </th>
                        <td>
                            {{ $userFavorite->user->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.userFavorite.fields.garage') }}
                        </th>
                        <td>
                            {{ $userFavorite->garage->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.userFavorite.fields.note') }}
                        </th>
                        <td>
                            {{ $userFavorite->note }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.user-favorites.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection