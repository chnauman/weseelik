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
        .garage-card .card {
            height: 100%;
            border: 1px solid #e0e0e0;
        }
        .garage-card .card-body {
            padding: 15px;
        }
        .garage-card .card-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .garage-card .card-text {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        .badge-new {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-bottom: 10px;
            display: inline-block;
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
        </form>

        <div id="resultContainer" class="mt-4" style="display: none;">
            <div class="alert alert-success">
                <strong id="resultMessage"></strong>
            </div>
        </div>

        <div id="garagesContainer" class="mt-4" style="display: none;">
            <h4>Fetched Garages:</h4>
            <div id="garagesGrid" class="row">
                <!-- Garage cards will be appended here -->
            </div>
        </div>

        <div id="errorContainer" class="alert alert-danger mt-4" style="display: none;">
            <strong>Error:</strong> <span id="errorMessage"></span>
        </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
    <script>
$(document).ready(function() {
    let isFetching = false;
    let statusPollInterval = null;
    let currentJobId = null;

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
        $('#errorContainer').hide();
        $('#resultContainer').hide();
        $('#garagesContainer').hide();
        $('#garagesGrid').empty();
        $('#fetchBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Fetching...');
        isFetching = true;

        // Start fetching - dispatch job
        $.ajax({
            url: '{{ route("fetch-garages.fetch") }}',
            method: 'POST',
            data: {
                area: area,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success && response.job_id) {
                    currentJobId = response.job_id;
                    // Start polling for status
                    startStatusPolling(response.job_id);
                } else {
                    showError(response.message || 'Failed to start garage fetching');
                    stopFetching();
                }
            },
            error: function(xhr) {
                let errorMsg = 'An error occurred while starting garage fetch';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showError(errorMsg);
                stopFetching();
            }
        });
    });

    function startStatusPolling(jobId) {
        // Clear any existing interval
        if (statusPollInterval) {
            clearInterval(statusPollInterval);
        }

        // Poll every 2 seconds
        statusPollInterval = setInterval(function() {
            checkJobStatus(jobId);
        }, 2000);

        // Initial check
        checkJobStatus(jobId);
    }

    function checkJobStatus(jobId) {
        $.ajax({
            url: '{{ route("fetch-garages.status") }}',
            method: 'GET',
            data: {
                job_id: jobId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.status;
                    const stats = response.stats || {};

                    if (status === 'processing') {
                        // Still processing, continue polling
                        // Don't show anything to user while processing
                    } else if (status === 'completed') {
                        // Job completed - show result
                        clearInterval(statusPollInterval);
                        statusPollInterval = null;
                        
                        const newCount = stats.new || 0;
                        const garages = response.garages || [];
                        showResult(newCount, garages);
                        stopFetching();
                    } else if (status === 'failed') {
                        // Job failed
                        clearInterval(statusPollInterval);
                        statusPollInterval = null;
                        
                        showError(response.error || 'Job failed');
                        stopFetching();
                    }
                } else {
                    // Job not found or expired
                    clearInterval(statusPollInterval);
                    statusPollInterval = null;
                    showError(response.message || 'Job status not found');
                    stopFetching();
                }
            },
            error: function(xhr) {
                // Continue polling on error (might be temporary)
                console.error('Error checking job status:', xhr);
            }
        });
    }

    function showResult(newCount, garages) {
        $('#resultMessage').text(`Successfully fetched ${newCount} new garage(s).`);
        $('#resultContainer').show();
        
        // Display garage cards
        if (garages.length > 0) {
            garages.forEach(function(garage) {
                addGarageCard(garage);
            });
            $('#garagesContainer').show();
        }
    }

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
                        ${garage.maps_share_link ? `<a href="${escapeHtml(garage.maps_share_link)}" target="_blank" class="btn btn-sm btn-primary mt-2"><i class="fa fa-map-marker"></i> View on Maps</a>` : ''}
                    </div>
                </div>
            </div>
        `;

        $('#garagesGrid').append(cardHtml);
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

    function showError(message) {
        $('#errorMessage').text(message);
        $('#errorContainer').show();
    }

    function stopFetching() {
        isFetching = false;
        currentJobId = null;
        if (statusPollInterval) {
            clearInterval(statusPollInterval);
            statusPollInterval = null;
        }
        $('#fetchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Fetch Garages');
    }
});
</script>
</body>
</html>