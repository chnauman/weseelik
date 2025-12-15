<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fetch Garages from Google Maps - Wiseelik</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet" />
    <link href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f5f7fa;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 20px;
        }
        .card-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .card-body {
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-map-marker-alt"></i> Fetch Garages from Google Maps</h3>
            </div>
            <div class="card-body">
        <form id="fetchGaragesForm">
            <div class="form-group">
                <label for="area">Select Area:</label>
                <select name="area" id="area" class="form-control" required>
                    <option value="">-- Select an Area --</option>
                    @foreach($kuwaitAreas as $areaName => $coordinates)
                        <option value="{{ $areaName }}">{{ $areaName }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="fetchBtn">
                <i class="fa fa-search"></i> Fetch Garages
            </button>
            <button type="button" class="btn btn-secondary" id="stopBtn" style="display: none;">
                <i class="fa fa-stop"></i> Stop Fetching
            </button>
        </form>

        <div id="progressContainer" style="display: none; margin-top: 20px;">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                     style="width: 0%" id="progressBar">
                    <span id="progressText">0%</span>
                </div>
            </div>
            <div class="mt-2">
                <small id="statsText">Processing...</small>
            </div>
        </div>

        <div id="garagesContainer" class="mt-4" style="display: none;">
            <h4>Fetched Garages:</h4>
            <div id="garagesGrid" class="row">
                <!-- Garage cards will be appended here -->
            </div>
        </div>

        <div id="errorContainer" class="alert alert-danger" style="display: none; margin-top: 20px;">
            <strong>Error:</strong> <span id="errorMessage"></span>
        </div>
    </div>
</div>

<style>
    .garage-card {
        margin-bottom: 20px;
        animation: fadeIn 0.5s ease-in;
    }
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .garage-card .card-body {
        padding: 15px;
    }
    .garage-card .card-title {
        font-size: 1.1rem;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .garage-card .card-text {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 5px;
    }
    .badge-new {
        background-color: #28a745;
        color: white;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 0.8rem;
    }
    </style>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
    <script>
$(document).ready(function() {
    let isFetching = false;
    let fetchTimeout = null;

    $('#fetchGaragesForm').on('submit', function(e) {
        e.preventDefault();
        
        if (isFetching) {
            return;
        }

        const area = $('#area').val();
        if (!area) {
            alert('Please select an area');
            return;
        }

        // Reset UI
        $('#garagesGrid').empty();
        $('#errorContainer').hide();
        $('#progressContainer').show();
        $('#garagesContainer').show();
        $('#fetchBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Fetching...');
        $('#stopBtn').show();
        isFetching = true;

        // Update progress
        $('#progressBar').css('width', '10%');
        $('#progressText').text('10%');
        $('#statsText').text('Starting to fetch garages...');

        // Start fetching
        $.ajax({
            url: '{{ route("fetch-garages.fetch") }}',
            method: 'POST',
            data: {
                area: area,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Update progress
                    $('#progressBar').css('width', '50%');
                    $('#progressText').text('50%');
                    $('#statsText').text('Processing garages...');

                    // Process garages with delay for visual effect
                    if (response.garages && response.garages.length > 0) {
                        let index = 0;
                        function addNextGarage() {
                            if (index < response.garages.length) {
                                addGarageCard(response.garages[index]);
                                index++;
                                
                                // Update progress
                                const progress = 50 + (index / response.garages.length) * 40;
                                $('#progressBar').css('width', progress + '%');
                                $('#progressText').text(Math.round(progress) + '%');
                                
                                // Add next garage with slight delay for visual effect
                                setTimeout(addNextGarage, 100);
                            } else {
                                // All garages added
                                $('#progressBar').css('width', '100%');
                                $('#progressText').text('100%');
                                updateStats(response.stats);
                                stopFetching();
                            }
                        }
                        addNextGarage();
                    } else {
                        // No new garages
                        updateStats(response.stats);
                        stopFetching();
                    }
                } else {
                    showError(response.message || 'Failed to fetch garages');
                    stopFetching();
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred while fetching garages';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showError(errorMsg);
                stopFetching();
            }
        });
    });

    $('#stopBtn').on('click', function() {
        stopFetching();
    });

    function addGarageCard(garage) {
        // Check if garage card already exists
        if ($('#garage-' + garage.id).length > 0) {
            return;
        }

        const cardHtml = `
            <div class="col-md-4 garage-card" id="garage-${garage.id}">
                <div class="card">
                    <div class="card-body">
                        <span class="badge-new">NEW</span>
                        <h5 class="card-title">${escapeHtml(garage.title || 'Unknown')}</h5>
                        ${garage.title_ar ? `<p class="card-text"><strong>Arabic:</strong> ${escapeHtml(garage.title_ar)}</p>` : ''}
                        ${garage.address ? `<p class="card-text"><strong>Address:</strong> ${escapeHtml(garage.address)}</p>` : ''}
                        ${garage.contact_no ? `<p class="card-text"><strong>Phone:</strong> ${escapeHtml(garage.contact_no)}</p>` : ''}
                        ${garage.rating ? `<p class="card-text"><strong>Rating:</strong> ${garage.rating}/5</p>` : ''}
                        ${garage.service_description ? `<p class="card-text"><strong>Services:</strong> ${escapeHtml(garage.service_description)}</p>` : ''}
                        ${garage.maps_share_link ? `<a href="${escapeHtml(garage.maps_share_link)}" target="_blank" class="btn btn-sm btn-primary mt-2">View on Maps</a>` : ''}
                    </div>
                </div>
            </div>
        `;

        $('#garagesGrid').append(cardHtml);
        
        // Scroll to bottom smoothly
        $('html, body').animate({
            scrollTop: $(document).height()
        }, 500);
    }

    function updateStats(stats) {
        const total = stats.processed || 0;
        const newCount = stats.new || 0;
        const skipped = stats.skipped || 0;
        const failed = stats.failed || 0;

        // Update progress bar (if we have a total estimate)
        // For now, just show stats
        $('#statsText').text(
            `Processed: ${total} | New: ${newCount} | Skipped: ${skipped} | Failed: ${failed}`
        );
    }

    function showError(message) {
        $('#errorMessage').text(message);
        $('#errorContainer').show();
    }

    function stopFetching() {
        isFetching = false;
        if (fetchTimeout) {
            clearTimeout(fetchTimeout);
            fetchTimeout = null;
        }
        $('#fetchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Fetch Garages');
        $('#stopBtn').hide();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
</body>
</html>