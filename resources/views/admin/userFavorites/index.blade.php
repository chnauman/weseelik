@extends('layouts.admin')
@section('content')
@can('user_favorite_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.user-favorites.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.userFavorite.title_singular') }}
            </a>
        </div>
    </div>
@endcan
<div class="card">
    <div class="card-header">
        {{ trans('cruds.userFavorite.title_singular') }} {{ trans('global.list') }}
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-UserFavorite">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.userFavorite.fields.id') }}
                        </th>
                        <th>
                            {{ trans('cruds.userFavorite.fields.user') }}
                        </th>
                        <th>
                            {{ trans('cruds.userFavorite.fields.garage') }}
                        </th>
                        <th>
                            {{ trans('cruds.userFavorite.fields.note') }}
                        </th>
                        <th>
                            &nbsp;
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($userFavorites as $key => $userFavorite)
                        <tr data-entry-id="{{ $userFavorite->id }}">
                            <td>

                            </td>
                            <td>
                                {{ $userFavorite->id ?? '' }}
                            </td>
                            <td>
                                {{ $userFavorite->user->name ?? '' }}
                            </td>
                            <td>
                                {{ $userFavorite->garage->title ?? '' }}
                            </td>
                            <td>
                                {{ $userFavorite->note ?? '' }}
                            </td>
                            <td>
                                @can('user_favorite_show')
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.user-favorites.show', $userFavorite->id) }}">
                                        {{ trans('global.view') }}
                                    </a>
                                @endcan

                                @can('user_favorite_edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.user-favorites.edit', $userFavorite->id) }}">
                                        {{ trans('global.edit') }}
                                    </a>
                                @endcan

                                @can('user_favorite_delete')
                                    <form action="{{ route('admin.user-favorites.destroy', $userFavorite->id) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="submit" class="btn btn-xs btn-danger" value="{{ trans('global.delete') }}">
                                    </form>
                                @endcan

                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>



@endsection
@section('scripts')
@parent
<script>
    $(function () {
  let dtButtons = $.extend(true, [], $.fn.dataTable.defaults.buttons)
@can('user_favorite_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.user-favorites.massDestroy') }}",
    className: 'btn-danger',
    action: function (e, dt, node, config) {
      var ids = $.map(dt.rows({ selected: true }).nodes(), function (entry) {
          return $(entry).data('entry-id')
      });

      if (ids.length === 0) {
        alert('{{ trans('global.datatables.zero_selected') }}')

        return
      }

      if (confirm('{{ trans('global.areYouSure') }}')) {
        $.ajax({
          headers: {'x-csrf-token': _token},
          method: 'POST',
          url: config.url,
          data: { ids: ids, _method: 'DELETE' }})
          .done(function () { location.reload() })
      }
    }
  }
  dtButtons.push(deleteButton)
@endcan

  $.extend(true, $.fn.dataTable.defaults, {
    orderCellsTop: true,
    order: [[ 1, 'desc' ]],
    pageLength: 100,
  });
  let table = $('.datatable-UserFavorite:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });

})

</script>
@endsection
