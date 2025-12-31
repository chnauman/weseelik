@extends('layouts.admin')
@section('content')
<div class="content">
    @if(session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <h2 class="mb-4">Analytics Dashboard</h2>

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card text-white bg-primary" style="cursor: pointer;" onclick="window.location.href='{{ route('admin.users.index') }}'">
                <div class="card-body">
                    <div class="text-value-lg">{{ number_format($totalUsers) }}</div>
                    <div>Total Users</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-white bg-info" style="cursor: pointer;" onclick="window.location.href='{{ route('admin.garages.index') }}'">
                <div class="card-body">
                    <div class="text-value-lg">{{ number_format($totalGarages) }}</div>
                    <div>Total Garages</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-white bg-success" style="cursor: pointer;" onclick="window.location.href='{{ route('admin.garages.index', ['verified' => 1]) }}'">
                <div class="card-body">
                    <div class="text-value-lg">{{ number_format($verifiedGarages) }}</div>
                    <div>Verified Garages</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-white bg-warning" style="cursor: pointer;" onclick="window.location.href='{{ route('admin.garages.index', ['unverified' => 1]) }}'">
                <div class="card-body">
                    <div class="text-value-lg">{{ number_format($unverifiedGarages) }}</div>
                    <div>Unverified Garages</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Summary Cards --}}
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card text-white bg-secondary-dark">
                <div class="card-body">
                    <div class="text-value-lg">{{ number_format($recentGarages) }}</div>
                    <div>Recent Garages (30 days)</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="text-value-lg">{{ $garagesByStatus->where('status', 'approved')->first()->total ?? 0 }}</div>
                    <div>Approved Garages</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row 1 --}}
    <div class="row mb-4">
        {{-- Garages by Area Pie Chart --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Garages Distribution by Area</h5>
                </div>
                <div class="card-body">
                    <canvas id="areaPieChart" height="300"></canvas>
                </div>
            </div>
        </div>

        {{-- Garages by Status Pie Chart --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Garages by Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusPieChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row 2 --}}
    <div class="row mb-4">
        {{-- Location Verification Chart --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Location Verification Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="locationVerifiedChart" height="300"></canvas>
                </div>
            </div>
        </div>

        {{-- Garages by Registration Type --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>Garages by Registration Type</h5>
                </div>
                <div class="card-body">
                    <canvas id="registeredByChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row 3 --}}
    <div class="row mb-4">
        {{-- Garages by Tag Bar Chart --}}
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5>Garages by Tag</h5>
                </div>
                <div class="card-body">
                    {{-- Filter Section --}}
                    <div class="tag-filter-section mb-4 p-3 bg-light rounded">
                        <div class="row align-items-end">
                            <div class="col-md-7">
                                <label for="tagSelector" class="form-label font-weight-bold mb-2">Filter by Tags:</label>
                                <div class="select2-wrapper">
                                    <select id="tagSelector" class="form-control" multiple>
                                        @foreach($allTags as $tagId => $tagTitle)
                                            <option value="{{ $tagId }}">{{ $tagTitle }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <small class="form-text text-muted mt-2">Select one or more tags to filter garages</small>
                            </div>
                            <div class="col-md-5">
                                <div class="d-flex flex-column">
                                    <button class="btn btn-primary btn-block mb-2" onclick="filterByTags()">
                                        <i class="fa fa-filter"></i> Filter Garages
                                    </button>
                                    <button class="btn btn-secondary btn-block" onclick="resetTagFilter()">
                                        <i class="fa fa-refresh"></i> Reset Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Total Count Display --}}
                    <div id="tagGarageCount" class="mb-3">
                        <div class="alert alert-info mb-0">
                            <h5 class="mb-0">
                                <i class="fa fa-building"></i> Total Garages: 
                                <span class="badge badge-primary badge-lg" id="tagCountDisplay">{{ $totalGarages }}</span>
                            </h5>
                        </div>
                    </div>
                    
                    {{-- Chart Container --}}
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="tagBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Area Distribution Table --}}
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5>Area Distribution Details</h5>
                </div>
                <div class="card-body" id="area-distribution-content">
                    @include('partials.area-distribution-table', ['garagesByAreaPaginated' => $garagesByAreaPaginated])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function() {
// CoreUI color palette
const coreuiColors = {
    primary: '#321fdb',
    secondary: '#ced2d8',
    success: '#2eb85c',
    danger: '#e55353',
    warning: '#f9b115',
    info: '#39afd1',
    light: '#e9ecef',
    dark: '#636f83'
};

// Store chart instances to prevent duplicates
const chartInstances = {};

// Area Pie Chart
const areaCanvas = document.getElementById('areaPieChart');
if (areaCanvas && !chartInstances.areaChart) {
    const areaCtx = areaCanvas.getContext('2d');
const areaLabels = @json($garagesByAreaWithPercent->pluck('area')->toArray());
const areaData = @json($garagesByAreaWithPercent->pluck('total')->toArray());
const areaPercentages = @json($garagesByAreaWithPercent->pluck('percentage')->toArray());

    chartInstances.areaChart = new Chart(areaCtx, {
        type: 'pie',
        data: {
            labels: areaLabels,
            datasets: [{
                data: areaData,
                backgroundColor: [
                    coreuiColors.primary,
                    coreuiColors.success,
                    coreuiColors.info,
                    coreuiColors.warning,
                    coreuiColors.danger,
                    coreuiColors.secondary,
                    coreuiColors.dark,
                    '#20c997',
                    '#6610f2',
                    '#e83e8c'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const index = context.dataIndex;
                            const percentage = areaPercentages[index];
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Status Pie Chart
const statusCanvas = document.getElementById('statusPieChart');
if (statusCanvas && !chartInstances.statusChart) {
    const statusCtx = statusCanvas.getContext('2d');
const statusLabels = @json($garagesByStatus->pluck('status')->map(function($status) { return \Illuminate\Support\Str::ucfirst($status); })->toArray());
const statusData = @json($garagesByStatus->pluck('total')->toArray());

    chartInstances.statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: [coreuiColors.success, coreuiColors.warning]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Location Verification Chart
const locationCanvas = document.getElementById('locationVerifiedChart');
if (locationCanvas && !chartInstances.locationChart) {
    const locationCtx = locationCanvas.getContext('2d');
const locationLabels = @json($garagesByLocationVerified->pluck('location_verified')->map(function($status) { 
    return str_replace('_', ' ', \Illuminate\Support\Str::ucfirst($status)); 
})->toArray());
const locationData = @json($garagesByLocationVerified->pluck('total')->toArray());

    chartInstances.locationChart = new Chart(locationCtx, {
        type: 'bar',
        data: {
            labels: locationLabels,
            datasets: [{
                label: 'Garages',
                data: locationData,
                backgroundColor: [
                    coreuiColors.success,
                    coreuiColors.warning,
                    coreuiColors.danger
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Tag Bar Chart
const tagCanvas = document.getElementById('tagBarChart');
if (tagCanvas && !chartInstances.tagChart) {
    // Destroy existing chart if it exists
    if (chartInstances.tagChart) {
        chartInstances.tagChart.destroy();
    }
    
    const tagCtx = tagCanvas.getContext('2d');
    const tagLabels = @json($garagesByTag->pluck('title')->toArray());
    const tagData = @json($garagesByTag->pluck('total')->toArray());
    const tagIds = @json($garagesByTag->pluck('id')->toArray());

    chartInstances.tagChart = new Chart(tagCtx, {
        type: 'bar',
        data: {
            labels: tagLabels,
            datasets: [{
                label: 'Number of Garages',
                data: tagData,
                backgroundColor: coreuiColors.info
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const tagId = tagIds[index];
                    window.location.href = '{{ route("admin.garages.index") }}?tags[]=' + tagId;
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Initialize Select2 for tag selector
$('#tagSelector').select2({
    placeholder: 'Select one or more tags to filter',
    allowClear: true,
    width: '100%',
    closeOnSelect: false,
    tags: false,
    tokenSeparators: [','],
    language: {
        noResults: function() {
            return "No tags found";
        }
    }
});

// Registered By Chart
const registeredCanvas = document.getElementById('registeredByChart');
if (registeredCanvas && !chartInstances.registeredChart) {
    const registeredCtx = registeredCanvas.getContext('2d');
const registeredLabels = @json($garagesByRegisteredBy->pluck('registered_by')->map(function($type) { return \Illuminate\Support\Str::ucfirst($type); })->toArray());
const registeredData = @json($garagesByRegisteredBy->pluck('total')->toArray());

    chartInstances.registeredChart = new Chart(registeredCtx, {
        type: 'doughnut',
        data: {
            labels: registeredLabels,
            datasets: [{
                data: registeredData,
                backgroundColor: [coreuiColors.primary, coreuiColors.success, coreuiColors.info]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Helper function for string manipulation
function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

}); // End of document ready

// Filter by Tags function (outside document ready so it's accessible globally)
function filterByTags() {
    const selectedTags = $('#tagSelector').val();
    if (!selectedTags || selectedTags.length === 0) {
        alert('Please select at least one tag');
        return;
    }
    
    // Build URL with multiple tag IDs
    const tagParams = selectedTags.map(tagId => 'tags[]=' + tagId).join('&');
    window.location.href = '{{ route("admin.garages.index") }}?' + tagParams;
}

function resetTagFilter() {
    $('#tagSelector').val(null).trigger('change');
    document.getElementById('tagCountDisplay').textContent = {{ $totalGarages }};
}

// AJAX Pagination for Area Distribution
$(document).ready(function() {
    // Intercept pagination clicks
    $(document).on('click', '#area-pagination-container .pagination a', function(e) {
        e.preventDefault();
        
        var url = $(this).attr('href');
        if (!url) return;
        
        // Show loading state
        var $container = $('#area-distribution-content');
        $container.addClass('loading');
        
        // Add loading spinner
        var $loading = $('<div class="text-center p-3" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i></div>');
        $container.append($loading);
        
        // Make AJAX request
        $.ajax({
            url: url + (url.indexOf('?') > -1 ? '&' : '?') + 'area_pagination=1',
            type: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                // Update the content
                $container.html(response.html);
                
                // Update URL without reload
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', url);
                }
                
                // Scroll to top of the table
                $('html, body').animate({
                    scrollTop: $('#area-distribution-content').offset().top - 100
                }, 300);
            },
            error: function(xhr, status, error) {
                console.error('Error loading pagination:', error);
                alert('Error loading page. Please try again.');
                $container.removeClass('loading');
                $loading.remove();
            },
            complete: function() {
                $container.removeClass('loading');
                $loading.remove();
            }
        });
    });
});
</script>
@endsection

@section('styles')
@parent
<style>
.card {
    margin-bottom: 1.5rem;
}
.text-value-lg {
    font-size: 2.5rem;
    font-weight: 600;
}
.card-body {
    padding: 1.5rem;
}
.bg-secondary-dark {
    background-color: #5a6268 !important;
}
.card-header h5 {
    margin: 0;
    display: inline-block;
}
.card-header-actions {
    float: right;
}
.tag-filter-section {
    border: 1px solid #e9ecef;
}
.tag-filter-section .form-label {
    color: #495057;
    font-size: 0.95rem;
}
/* Select2 Styling */
.select2-wrapper {
    width: 100%;
}
.select2-container {
    width: 100% !important;
    z-index: 1000;
}
.select2-container--default .select2-selection--multiple {
    border: 1px solid #ced2d8;
    border-radius: 0.25rem;
    min-height: 38px;
    padding: 2px 5px;
    background-color: #fff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.select2-container--default .select2-selection--multiple:focus {
    border-color: #321fdb;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(50, 31, 219, 0.25);
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #321fdb;
    border: 1px solid #321fdb;
    color: #fff;
    padding: 3px 8px;
    margin: 3px 3px 3px 0;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    line-height: 1.5;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #fff;
    margin-right: 5px;
    cursor: pointer;
    font-weight: bold;
    opacity: 0.9;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #fff;
    opacity: 1;
}
.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #321fdb;
    box-shadow: 0 0 0 0.2rem rgba(50, 31, 219, 0.25);
}
.select2-container--default .select2-search--inline .select2-search__field {
    margin-top: 3px;
    padding: 2px 5px;
    height: auto;
    border: none;
    outline: none;
    box-shadow: none;
}
.select2-container--default .select2-search--inline .select2-search__field:focus {
    border: none;
    outline: none;
    box-shadow: none;
}
.select2-dropdown {
    border: 1px solid #ced2d8;
    border-radius: 0.25rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
.select2-container--default .select2-results__option {
    padding: 8px 12px;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #321fdb;
    color: #fff;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #e9ecef;
    color: #495057;
}
.select2-container--default .select2-results__option[aria-selected=true]:hover {
    background-color: #dae0e5;
}
.select2-container .select2-selection--multiple .select2-selection__rendered {
    padding: 0;
}
#area-distribution-content {
    transition: opacity 0.3s ease;
    position: relative;
}
#area-distribution-content.loading {
    opacity: 0.6;
    pointer-events: none;
}
.chart-container {
    min-height: 400px;
}
.badge-lg {
    font-size: 1.1rem;
    padding: 0.5rem 0.75rem;
}
.pagination {
    margin-top: 1rem;
    margin-bottom: 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    list-style: none;
    padding-left: 0;
}
.page-item {
    display: inline-block;
}
.page-item:first-child .page-link {
    margin-left: 0;
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}
.page-item:last-child .page-link {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}
.page-link {
    position: relative;
    display: block;
    padding: 0.5rem 0.75rem;
    margin-left: -1px;
    line-height: 1.25;
    color: #321fdb;
    text-decoration: none;
    background-color: #fff;
    border: 1px solid #ced2d8;
}
.page-link:hover {
    color: #321fdb;
    background-color: #e9ecef;
    border-color: #ced2d8;
    z-index: 2;
}
.page-link:focus {
    z-index: 3;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(50, 31, 219, 0.25);
}
.page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background-color: #321fdb;
    border-color: #321fdb;
}
.page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    cursor: auto;
    background-color: #fff;
    border-color: #ced2d8;
}
</style>
@endsection
