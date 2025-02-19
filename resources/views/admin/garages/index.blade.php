@extends('layouts.admin')
@section('content')
@can('garage_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.garages.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.garage.title_singular') }}
            </a>
        </div>
    </div>
@endcan
<div class="card">
    <div class="card-header">
        {{ trans('cruds.garage.title_singular') }} {{ trans('global.list') }}
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-Garage">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.id') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.user') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.title') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.description') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.service_description') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.contact_no') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.address') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.city') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.country') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.latitude') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.longitude') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.working_hours') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.opening_time') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.closing_time') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.status') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.logo') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.storefront_image') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.gallery') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.tag') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.language') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.specialization') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.services') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.category') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.rating') }}
                        </th>
                        <th>
                            &nbsp;
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($garages as $key => $garage)
                        <tr data-entry-id="{{ $garage->id }}">
                            <td>

                            </td>
                            <td>
                                {{ $garage->id ?? '' }}
                            </td>
                            <td>
                                {{ $garage->user->name ?? '' }}
                            </td>
                            <td>
                                {{ $garage->title ?? '' }}
                            </td>
                            <td>
                                {{ $garage->description ?? '' }}
                            </td>
                            <td>
                                {{ $garage->service_description ?? '' }}
                            </td>
                            <td>
                                {{ $garage->contact_no ?? '' }}
                            </td>
                            <td>
                                {{ $garage->address ?? '' }}
                            </td>
                            <td>
                                {{ $garage->city ?? '' }}
                            </td>
                            <td>
                                {{ $garage->country->name ?? '' }}
                            </td>
                            <td>
                                {{ $garage->latitude ?? '' }}
                            </td>
                            <td>
                                {{ $garage->longitude ?? '' }}
                            </td>
                            <td>
                                {{ $garage->working_hours ?? '' }}
                            </td>
                            <td>
                                {{ $garage->opening_time ?? '' }}
                            </td>
                            <td>
                                {{ $garage->closing_time ?? '' }}
                            </td>
                            <td>
                                {{ App\Models\Garage::STATUS_SELECT[$garage->status] ?? '' }}
                            </td>
                            <td>
                                @if($garage->logo)
                                    <a href="{{ $garage->logo->getUrl() }}" target="_blank" style="display: inline-block">
                                        <img src="{{ $garage->logo->getUrl('thumb') }}">
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if($garage->storefront_image)
                                    <a href="{{ $garage->storefront_image->getUrl() }}" target="_blank" style="display: inline-block">
                                        <img src="{{ $garage->storefront_image->getUrl('thumb') }}">
                                    </a>
                                @endif
                            </td>
                            <td>
                                @foreach($garage->gallery as $key => $media)
                                    <a href="{{ $media->getUrl() }}" target="_blank" style="display: inline-block">
                                        <img src="{{ $media->getUrl('thumb') }}">
                                    </a>
                                @endforeach
                            </td>
                            <td>
                                @foreach($garage->tags as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                @foreach($garage->languages as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                @foreach($garage->specializations as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                @foreach($garage->services as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                @foreach($garage->categories as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                {{ $garage->rating ?? '' }}
                            </td>
                            <td>
                                @can('garage_show')
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.garages.show', $garage->id) }}">
                                        {{ trans('global.view') }}
                                    </a>
                                @endcan

                                @can('garage_edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.garages.edit', $garage->id) }}">
                                        {{ trans('global.edit') }}
                                    </a>
                                @endcan

                                @can('garage_delete')
                                    <form action="{{ route('admin.garages.destroy', $garage->id) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
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
@can('garage_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.garages.massDestroy') }}",
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
  let table = $('.datatable-Garage:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });
  
})

</script>
@endsection