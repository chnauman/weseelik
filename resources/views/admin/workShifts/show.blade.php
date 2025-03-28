@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.workShift.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.work-shifts.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.workShift.fields.id') }}
                        </th>
                        <td>
                            {{ $workShift->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.workShift.fields.opening_time') }}
                        </th>
                        <td>
                            {{ $workShift->opening_time }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.workShift.fields.closing_time') }}
                        </th>
                        <td>
                            {{ $workShift->closing_time }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.workShift.fields.day_of_week') }}
                        </th>
                        <td>
                            {{ $workShift->day_of_week }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.workShift.fields.garage') }}
                        </th>
                        <td>
                            {{ $workShift->garage->title ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.work-shifts.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection