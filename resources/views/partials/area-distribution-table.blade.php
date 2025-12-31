<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Area</th>
                <th>Total Garages</th>
                <th>Percentage</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($garagesByAreaPaginated as $area)
                <tr>
                    <td>{{ $area->area ?? 'N/A' }}</td>
                    <td>{{ $area->total }}</td>
                    <td>{{ $area->percentage }}%</td>
                    <td>
                        <a href="{{ route('admin.garages.index', ['area' => $area->area]) }}" class="btn btn-sm btn-primary">View Garages</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3 d-flex justify-content-center" id="area-pagination-container">
    {{ $garagesByAreaPaginated->links('pagination.default') }}
</div>

