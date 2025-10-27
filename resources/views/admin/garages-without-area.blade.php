@extends('layouts.admin')

@section('content')
<div class="content">
    <div class="card">
        <div class="card-header">
            Garages Without Area
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Area</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Open in Google Maps</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($garages as $garage)
                        <tr>
                            <td>{{ $garage->id }}</td>
                            <td>{{ $garage->title }}</td>
                            <td>{{ $garage->area }}</td>
                            <td>{{ $garage->latitude }}</td>
                            <td>{{ $garage->longitude }}</td>
                            <td>
                                @if($garage->latitude && $garage->longitude)
                                    <a href="https://www.google.com/maps?q={{ $garage->latitude }},{{ $garage->longitude }}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-primary">
                                        View on Map
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No garages without area found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
