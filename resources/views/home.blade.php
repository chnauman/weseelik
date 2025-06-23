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
@endsection
@section('scripts')
@parent

@endsection