@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    Dashboard
                </div>

                <div class="card-body">
                    @if(session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <h4>Garages Count by Area</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Total Garages</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($garagesByArea as $row)
                                <tr>
                                    <td>{{ $row->area ?? 'N/A' }}</td>
                                    <td>{{ $row->total }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2">No data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- New: All Garages with Filters --}}
<div class="content mt-4">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    All Garages (with Filters)
                </div>
                <div class="card-body">
                    @php
                        $allTags = \App\Models\Tag::pluck('title');
                        $allSpecializations = \App\Models\Specialization::pluck('title');
                    @endphp
                    <table id="all-garages-table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Area</th>
                                <th>Status</th>
                                <th>Owner</th>
                                <th>Tags</th>
                                <th>Specializations</th>
                                <th>Created At</th>
                                {{-- Add more columns as needed --}}
                            </tr>
                            <tr>
                                <th><input type="text" placeholder="Search ID" class="form-control form-control-sm" /></th>
                                <th><input type="text" placeholder="Search Name" class="form-control form-control-sm" /></th>
                                <th>
                                    <select class="form-control form-control-sm">
                                        <option value="">All</option>
                                        @foreach($areas ?? [] as $area)
                                            <option value="{{ $area }}">{{ $area }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th>
                                    <select class="form-control form-control-sm">
                                        <option value="">All</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                    </select>
                                </th>
                                <th><input type="text" placeholder="Search Owner" class="form-control form-control-sm" /></th>
                                <th>
                                    <select class="form-control form-control-sm">
                                        <option value="">All</option>
                                        @foreach(($allTags ?? collect()) as $tagTitle)
                                            <option value="{{ $tagTitle }}">{{ $tagTitle }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th>
                                    <select class="form-control form-control-sm">
                                        <option value="">All</option>
                                        @foreach(($allSpecializations ?? collect()) as $specTitle)
                                            <option value="{{ $specTitle }}">{{ $specTitle }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th><input type="text" placeholder="Search Date" class="form-control form-control-sm" /></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Models\Garage::with(['user', 'tags', 'specializations'])->get() as $garage)
                                <tr>
                                    <td>{{ $garage->id }}</td>
                                    <td>{{ $garage->title }}</td>
                                    <td>{{ $garage->area }}</td>
                                    <td>{{ $garage->status }}</td>
                                    <td>{{ $garage->user->name ?? 'N/A' }}</td>
                                    <td>{{ $garage->tags->pluck('title')->implode(', ') }}</td>
                                    <td>{{ $garage->specializations->pluck('title')->implode(', ') }}</td>
                                    <td>{{ $garage->created_at }}</td>
                                    {{-- Add more columns as needed --}}
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#all-garages-table').DataTable({
        orderCellsTop: true,
        fixedHeader: true
    });

    // Apply the search
    $('#all-garages-table thead tr:eq(1) th').each(function(i) {
        $('input, select', this).on('keyup change', function() {
            if (table.column(i).search() !== this.value) {
                table
                    .column(i)
                    .search(this.value)
                    .draw();
            }
        });
    });
});
</script>
@endsection