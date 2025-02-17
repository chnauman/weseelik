@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('global.show') }} {{ trans('cruds.message.title') }}
    </div>

    <div class="card-body">
        <div class="form-group">
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
            <table class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.id') }}
                        </th>
                        <td>
                            {{ $message->id }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.sender') }}
                        </th>
                        <td>
                            {{ $message->sender->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.receiver') }}
                        </th>
                        <td>
                            {{ $message->receiver->name ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.message') }}
                        </th>
                        <td>
                            {{ $message->message }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.status') }}
                        </th>
                        <td>
                            {{ App\Models\Message::STATUS_SELECT[$message->status] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <th>
                            {{ trans('cruds.message.fields.tagged_message') }}
                        </th>
                        <td>
                            {{ $message->tagged_message }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="form-group">
                <a class="btn btn-default" href="{{ route('admin.messages.index') }}">
                    {{ trans('global.back_to_list') }}
                </a>
            </div>
        </div>
    </div>
</div>



@endsection