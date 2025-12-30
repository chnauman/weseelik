@extends('layouts.admin')
@section('content')
@can('garage_create')
    <div style="margin-bottom: 10px;" class="row">
        <div class="col-lg-12">
            <a class="btn btn-success" href="{{ route('admin.garages.create') }}">
                {{ trans('global.add') }} {{ trans('cruds.garage.title_singular') }}
            </a>
            @can('garage_edit')
            <a class="btn btn-info" href="{{ route('admin.garages.assignForm') }}">
                {{ trans('global.assign') }} {{ trans('cruds.garage.title_singular') }}
            </a>
            @endcan
        </div>
    </div>
@endcan
<div class="card">
    <div class="card-header">
        {{ trans('cruds.garage.title_singular') }} {{ trans('global.list') }}
        @if(request()->hasAny(['status', 'location_verified', 'area', 'tags', 'verified', 'unverified']))
            <a href="{{ route('admin.garages.index') }}" class="btn btn-sm btn-secondary float-right">Clear Filters</a>
        @endif
    </div>

    <div class="card-body">
        {{-- Filter Section --}}
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Filters</h6>
                        <form method="GET" action="{{ route('admin.garages.index') }}" id="filterForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-control form-control-sm">
                                        <option value="">All</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="location_verified">Location Verified</label>
                                    <select name="location_verified" id="location_verified" class="form-control form-control-sm">
                                        <option value="">All</option>
                                        <option value="verified" {{ request('location_verified') == 'verified' ? 'selected' : '' }}>Verified</option>
                                        <option value="human_verification_required" {{ request('location_verified') == 'human_verification_required' ? 'selected' : '' }}>Human Verification Required</option>
                                        <option value="unverified" {{ request('location_verified') == 'unverified' ? 'selected' : '' }}>Unverified</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="area">Area</label>
                                    <select name="area" id="area" class="form-control form-control-sm">
                                        <option value="">All</option>
                                        @foreach($areas ?? [] as $area)
                                            <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="tags">Tags</label>
                                    <select name="tags[]" id="tags" class="form-control form-control-sm" multiple>
                                        @foreach($tags ?? [] as $tagId => $tagTitle)
                                            <option value="{{ $tagId }}" {{ in_array($tagId, (array)request('tags', [])) ? 'selected' : '' }}>{{ $tagTitle }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                                    <a href="{{ route('admin.garages.index') }}" class="btn btn-sm btn-secondary">Reset</a>
                                    @if(request()->hasAny(['status', 'location_verified', 'area', 'tags', 'verified', 'unverified']))
                                        <span class="badge badge-info ml-2">
                                            {{ $garages->count() }} garage(s) found
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class=" table table-bordered table-striped table-hover datatable datatable-Garage">
                <thead>
                    <tr>
                        <th width="10">

                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.title') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.storefront_image') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.date') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.surveyor') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.description') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.language') }}
                        </th>
                        <th>
                            {{ trans('cruds.garage.fields.status') }}
                        </th>
                        <th>
                            Confidence Level
                        </th>
                        <th>
                            Location Verification
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
                                {{ $garage->title ?? '' }}
                            </td>
                            <td>
                                @if($garage->storefront_image)
                                    <a href="{{ $garage->storefront_image->getUrl() }}" target="_blank" style="display: inline-block">
                                        <img src="{{ $garage->storefront_image->getUrl('thumb') }}">
                                    </a>
                                @endif
                            </td>
                            <td>
                                {{ $garage->updated_at ? $garage->updated_at->format('Y-m-d') : '' }}
                            </td>
                            <td>
                                {{ $garage->surveyor->name ?? '' }}
                            </td>
                            <td>
                                {{ $garage->description ?? '' }}
                            </td>
                            <td>
                                @foreach($garage->languages as $key => $item)
                                    <span class="badge badge-info">{{ $item->title }}</span>
                                @endforeach
                            </td>
                            <td>
                                {{ App\Models\Garage::STATUS_SELECT[$garage->status] ?? '' }}
                            </td>
                            <td class="confidence-value" data-garage-id="{{ $garage->id }}">
                                @if($garage->confidence !== null)
                                    {{ $garage->confidence }}%
                                @endif
                            </td>
                            <td class="location-verified" data-garage-id="{{ $garage->id }}">
                                @if($garage->location_verified)
                                    @if($garage->location_verified === 'verified')
                                        <span class="badge badge-success">Verified</span>
                                    @elseif($garage->location_verified === 'human_verification_required')
                                        <span class="badge badge-warning">Human Verification Required</span>
                                    @elseif($garage->location_verified === 'unverified')
                                        <span class="badge badge-danger">Unverified</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $garage->location_verified)) }}</span>
                                    @endif
                                @endif
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

                                @if(($garage->confidence === null || $garage->confidence < 80))
                                    <button type="button" class=" text-white btn btn-xs btn-warning verify-btn" data-garage-id="{{ $garage->id }}" data-garage-title="{{ $garage->title }}">
                                        Verify
                                    </button>
                                @endif

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function () {
        // Initialize Select2 for tags
        $('#tags').select2({
            placeholder: 'Select tags',
            allowClear: true,
            width: '100%'
        });
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
    pageLength: 10,
  });
  let table = $('.datatable-Garage:not(.ajaxTable)').DataTable({ buttons: dtButtons })
  $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e){
      $($.fn.dataTable.tables(true)).DataTable()
          .columns.adjust();
  });
  
  // Handle Verify button click
  $(document).on('click', '.verify-btn', function() {
    var button = $(this);
    var garageId = button.data('garage-id');
    var garageTitle = button.data('garage-title');
    
    // Disable button and show loading state
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
    
    // Call the API
    $.ajax({
      url: '/api/garage-match-url/' + garageId,
      method: 'GET',
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          // Use match_percentage directly from response (always at root level)
          var confidence = response.match_percentage !== undefined && response.match_percentage !== null 
            ? response.match_percentage 
            : 0;
          
          // Use location_verified directly from API response
          var locationVerified = response.garage && response.garage.location_verified 
            ? response.garage.location_verified 
            : null;
          
          // Determine match source for success message
          var matchSource = '';
          var directMatchPercentage = response.direct_match && response.direct_match.match_percentage !== undefined 
            ? response.direct_match.match_percentage 
            : 0;
          var highestNearbyMatchPercentage = response.nearby_places && response.nearby_places.length > 0 
            ? response.nearby_places[0].match_percentage 
            : 0;
          
          if (confidence > 0) {
            if (directMatchPercentage === confidence && highestNearbyMatchPercentage === confidence) {
              // Both have same percentage (tie)
              matchSource = ' (from direct match and nearby places)';
            } else if (directMatchPercentage === confidence) {
              matchSource = ' (from direct match)';
            } else if (highestNearbyMatchPercentage === confidence) {
              matchSource = ' (from nearby places)';
            } else {
              matchSource = '';
            }
          }
          
          // Update confidence value
          var confidenceCell = $('.confidence-value[data-garage-id="' + garageId + '"]');
          confidenceCell.text(confidence + '%');
          
          // Update location verified badge
          var locationCell = $('.location-verified[data-garage-id="' + garageId + '"]');
          var badgeClass = 'badge-secondary';
          var badgeText = 'Unknown';
          
          if (locationVerified === 'verified') {
            badgeClass = 'badge-success';
            badgeText = 'Verified';
          } else if (locationVerified === 'human_verification_required') {
            badgeClass = 'badge-warning';
            badgeText = 'Human Verification Required';
          } else if (locationVerified === 'unverified') {
            badgeClass = 'badge-danger';
            badgeText = 'Unverified';
          } else if (locationVerified) {
            badgeText = locationVerified.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
          }
          
          locationCell.html('<span class="badge ' + badgeClass + '">' + badgeText + '</span>');
          
          // Hide verify button if confidence is >= 80
          if (confidence >= 80) {
            button.remove();
          } else {
            button.prop('disabled', false).html('Verify');
          }
          
          // Show success message with match source
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'Garage verified successfully! Confidence: ' + confidence + '%' + matchSource,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.message || 'Failed to verify garage',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
          });
          button.prop('disabled', false).html('Verify');
        }
      },
      error: function(xhr) {
        var errorMessage = 'Failed to verify garage';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage,
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        });
        button.prop('disabled', false).html('Verify');
      }
    });
  });
  
})

</script>
@endsection