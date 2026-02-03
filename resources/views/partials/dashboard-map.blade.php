{{-- Dashboard map section: garages on map (green = title match, red = no match) --}}
<div class="row mb-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5>Garages Map</h5>
            </div>
            <div class="card-body">
                @if(empty($mapKey))
                    <div class="alert alert-warning mb-0">Google Maps API key is not configured. Set GOOGLE_MAPS_API_KEY in .env.</div>
                @else
                    <p class="text-muted small mb-2">Green pin = location title matches DB; Red = no match. Click a pin for details.</p>
                    <div class="dashboard-map-legend mb-3">
                        <span class="map-legend-item">
                            <span class="map-legend-pin map-legend-pin--green" aria-hidden="true"></span>
                            <span>Title matches</span>
                        </span>
                        <span class="map-legend-item">
                            <span class="map-legend-pin map-legend-pin--red" aria-hidden="true"></span>
                            <span>Title does not match</span>
                        </span>
                    </div>
                    <div id="dashboard-map" class="dashboard-map-container"></div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(!empty($mapKey))
<script type="application/json" id="dashboard-markers-data">{!! $mapMarkersJson !!}</script>
<script>
(function() {
    const KUWAIT_CENTER = { lat: 29.3759, lng: 47.9774 };
    const markersData = JSON.parse(document.getElementById('dashboard-markers-data').textContent);
    const mapKey = @json($mapKey);

    function initDashboardMap() {
        const mapEl = document.getElementById('dashboard-map');
        if (!mapEl) return;
        const map = new google.maps.Map(mapEl, {
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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
            const clickContent = '<div style="min-width: 180px; color: #333;">' + labelLines.join('<br>') + '</div>';

            marker.addListener('click', function() {
                infoWindow.setContent(clickContent);
                infoWindow.open(map, marker);
            });
        });
    }

    function loadGoogleMapsDashboard() {
        if (window.google && window.google.maps) {
            initDashboardMap();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapKey) + '&callback=initDashboardMap';
        script.async = true;
        script.defer = true;
        window.initDashboardMap = initDashboardMap;
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadGoogleMapsDashboard);
    } else {
        loadGoogleMapsDashboard();
    }
})();
</script>
@endif
