@extends('layouts.user')

@section('title', 'Garages Map | Kuwait | Wiseelik')

@section('styles')
<style>
    #map {
        width: 100%;
        height: calc(100vh - 120px);
        min-height: 400px;
    }
    .map-page-header {
        padding: 1rem 0;
        margin-bottom: 0;
    }
    .map-page-header h1 {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }
    .map-legend {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 0.5rem;
    }
    .map-legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    .map-legend-pin {
        width: 24px;
        height: 32px;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
    }
    .map-legend-pin--green {
        background-image: url('https://maps.google.com/mapfiles/ms/icons/green.png');
    }
    .map-legend-pin--red {
        background-image: url('https://maps.google.com/mapfiles/ms/icons/red-dot.png');
    }
</style>
@endsection

@section('content')
<div class="map-page-header">
    <div class="container">
        <h1>Garages in Kuwait</h1>
        <p class="text-muted mb-0">Click a pin to see garage title (EN & AR). Green = location title matches DB; Red = no match.</p>
        <div class="map-legend">
            <span class="map-legend-item">
                <span class="map-legend-pin map-legend-pin--green" aria-hidden="true"></span>
                <span>Title matches</span>
            </span>
            <span class="map-legend-item">
                <span class="map-legend-pin map-legend-pin--red" aria-hidden="true"></span>
                <span>Title does not match</span>
            </span>
        </div>
    </div>
</div>

<div class="container-fluid px-0">
    @if(empty($mapKey))
        <div class="alert alert-warning m-3">Google Maps API key is not configured. Set GOOGLE_MAPS_API_KEY in .env.</div>
    @else
        <div id="map"></div>
    @endif
</div>

@if(!empty($mapKey))
<script type="application/json" id="markers-data">{!! $markersJson !!}</script>
<script>
(function() {
    const KUWAIT_CENTER = { lat: 29.3759, lng: 47.9774 };
    const markersData = JSON.parse(document.getElementById('markers-data').textContent);
    const mapKey = @json($mapKey);

    function initMap() {
        const map = new google.maps.Map(document.getElementById('map'), {
            center: KUWAIT_CENTER,
            zoom: 10,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
        });

        const infoWindow = new google.maps.InfoWindow();

        function buildHoverText(item) {
            const parts = [];
            if (item.title) parts.push('Title: ' + item.title);
            if (item.title_ar) parts.push('Arabic: ' + item.title_ar);
            return parts.length ? parts.join('\n') : 'Garage';
        }

        markersData.forEach(function(item) {
            const position = { lat: item.lat, lng: item.lng };
            const isGreen = item.title_matches === true;

            const marker = new google.maps.Marker({
                position: position,
                map: map,
                icon: isGreen
                    ? 'https://maps.google.com/mapfiles/ms/icons/green.png'
                    : 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                title: buildHoverText(item),
            });

            const labelLines = [];
            if (item.title) labelLines.push('<strong>' + escapeHtml(item.title) + '</strong>');
            if (item.title_ar) labelLines.push('<span dir="rtl" lang="ar" style="display:block;">' + escapeHtml(item.title_ar) + '</span>');
            if (item.maps_share_link) {
                labelLines.push('<a href="' + escapeHtml(item.maps_share_link) + '" target="_blank" rel="noopener">Open in Google Maps</a>');
            }
            const clickContent = '<div style="min-width: 180px; color: #333; font-family: system-ui, sans-serif;">' + labelLines.join('<br>') + '</div>';

            marker.addListener('click', function() {
                infoWindow.setContent(clickContent);
                infoWindow.open(map, marker);
            });
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function loadGoogleMaps() {
        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapKey) + '&callback=initMap';
        script.async = true;
        script.defer = true;
        window.initMap = initMap;
        document.head.appendChild(script);
    }

    loadGoogleMaps();
})();
</script>
@endif
@endsection
