@extends('layouts.admin')
@section('content')
@can('booking_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.bookings.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.booking.title_singular') }}
            </a>
        </div>
    </div>
@endcan
<div class="card">
    <div class="card-header">
        {{ trans('cruds.booking.title_singular') }} {{ trans('global.list') }}
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-Booking">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.id') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.reference_no') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.user') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.garage') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.appointment_time') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.status') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.processing_status') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.garage_remarks') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.client_remarks') }}
                        </th>
                        <th>
                            {{ trans('cruds.booking.fields.client_rating') }}
                        </th>
                        <th>
                            &nbsp;
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $key => $booking)
                        <tr data-entry-id="{{ $booking->id }}">
                            <td>

                            </td>
                            <td>
                                {{ $booking->id ?? '' }}
                            </td>
                            <td>
                                {{ $booking->reference_no ?? '' }}
                            </td>
                            <td>
                                {{ $booking->user->name ?? '' }}
                            </td>
                            <td>
                                {{ $booking->garage->title ?? '' }}
                            </td>
                            <td>
                                {{ $booking->appointment_time ?? '' }}
                            </td>
                            <td>
                                {{ App\Models\Booking::STATUS_SELECT[$booking->status] ?? '' }}
                            </td>
                            <td>
                                {{ App\Models\Booking::PROCESSING_STATUS_SELECT[$booking->processing_status] ?? '' }}
                            </td>
                            <td>
                                {{ $booking->garage_remarks ?? '' }}
                            </td>
                            <td>
                                {{ $booking->client_remarks ?? '' }}
                            </td>
                            <td>
                                {{ $booking->client_rating ?? '' }}
                            </td>
                            <td>
                                @can('booking_show')
                                    <a class="btn btn-xs btn-primary" href="{{ route('admin.bookings.show', $booking->id) }}">
                                        {{ trans('global.view') }}
                                    </a>
                                @endcan

                                @can('booking_edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('admin.bookings.edit', $booking->id) }}">
                                        {{ trans('global.edit') }}
                                    </a>
                                @endcan

                                @can('booking_delete')
                                    <form action="{{ route('admin.bookings.destroy', $booking->id) }}" method="POST" onsubmit="return confirm('{{ trans('global.areYouSure') }}');" style="display: inline-block;">
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
@can('booking_delete')
  let deleteButtonTrans = '{{ trans('global.datatables.delete') }}'
  let deleteButton = {
    text: deleteButtonTrans,
    url: "{{ route('admin.bookings.massDestroy') }}",
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
  let table = $('.datatable-Booking:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });

})

</script>
@endsection
