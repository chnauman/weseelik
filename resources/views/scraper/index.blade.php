@extends('layouts.user')

@section('title', ' Wiseelik')

@section('styles')
<style>
    .scraper-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
    }
    .scraper-form {
        background: #fff;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #333;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1rem;
    }
    .btn-scrape {
        background: #007bff;
        color: white;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-scrape:hover {
        background: #0056b3;
    }
    .btn-scrape:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 2rem;
    }
    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .progress-text {
        margin-top: 1rem;
        color: #666;
    }
    .results-container {
        margin-top: 2rem;
    }
    .places-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    .place-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .place-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    .place-card-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: #f0f0f0;
    }
    .place-card-body {
        padding: 1.25rem;
    }
    .place-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
    }
    .place-card-address {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }
    .place-card-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .rating-stars {
        color: #ffc107;
    }
    .rating-text {
        color: #666;
        font-size: 0.9rem;
    }
    .place-card-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .btn-view-maps {
        flex: 1;
        background: #28a745;
        color: white;
        padding: 0.5rem;
        border: none;
        border-radius: 6px;
        text-decoration: none;
        text-align: center;
        font-size: 0.9rem;
        transition: background 0.3s;
    }
    .btn-view-maps:hover {
        background: #218838;
        color: white;
    }
    .btn-load-more {
        display: none;
        margin: 2rem auto;
        padding: 0.75rem 2rem;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-load-more:hover {
        background: #0056b3;
    }
    .no-results {
        text-align: center;
        padding: 3rem;
        color: #666;
    }
    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 1rem;
        border-radius: 6px;
        margin-top: 1rem;
    }
</style>
@endsection

@section('content')
<div class="scraper-container">
    <h1 style="text-align: center; margin-bottom: 2rem;">Google Places Scraper</h1>
    
    <div class="scraper-form">
        <form id="scraperForm">
            <div class="form-group">
                <label for="city">Select City</label>
                <select name="city" id="city" class="form-control" required>
                    <option value="">-- Select City --</option>
                    <option value="All Kuwait">All Kuwait</option>
                    <option value="Kuwait City">Kuwait City</option>
                    <option value="Shuaikh">Shuaikh</option>
                    <option value="Hawalli">Hawalli</option>
                    <option value="Salmiya">Salmiya</option>
                    <option value="Farwaniya">Farwaniya</option>
                    <option value="Ahmadi">Ahmadi</option>
                    <option value="Jahra">Jahra</option>
                    <option value="Mubarak Al-Kabeer">Mubarak Al-Kabeer</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="category">Select Category</label>
                <select name="category" id="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <option value="garages">Garages</option>
                    <option value="petrol_pumps">Petrol Pumps</option>
                    <option value="restaurants">Restaurants</option>
                    <option value="atms">ATMs</option>
                    <option value="hospitals">Hospitals</option>
                    <option value="schools">Schools</option>
                    <option value="malls">Malls</option>
                    <option value="hotels">Hotels</option>
                </select>
            </div>
            
            <button type="submit" class="btn-scrape" id="scrapeBtn">
                Scrape Data
            </button>
        </form>
    </div>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <div class="progress-text" id="progressText">Scraping places...</div>
    </div>

    <div class="results-container" id="resultsContainer" style="display: none;">
        <div id="errorMessage" class="error-message" style="display: none;"></div>
        <div class="places-grid" id="placesGrid"></div>
        <button class="btn-load-more" id="loadMoreBtn" onclick="loadMore()">Load More</button>
    </div>
</div>

<script>
let currentPage = 1;
let currentCity = '';
let currentCategory = '';
let hasMore = false;
let nextPageToken = null;

document.getElementById('scraperForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const city = document.getElementById('city').value;
    const category = document.getElementById('category').value;
    
    if (!city || !category) {
        alert('Please select both city and category');
        return;
    }
    
    // Reset
    currentPage = 1;
    currentCity = city;
    currentCategory = category;
    nextPageToken = null;
    document.getElementById('placesGrid').innerHTML = '';
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('loadMoreBtn').style.display = 'none';
    
    // Show loading
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('scrapeBtn').disabled = true;
    document.getElementById('progressText').textContent = 'Searching for places...';
    
    scrapePlaces(city, category, 1);
});

function scrapePlaces(city, category, page) {
    fetch('{{ route("scraper.scrape") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            city: city,
            category: category,
            page: page
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('scrapeBtn').disabled = false;
        
        if (data.success) {
            document.getElementById('resultsContainer').style.display = 'block';
            document.getElementById('errorMessage').style.display = 'none';
            
            if (data.places.length === 0) {
                document.getElementById('placesGrid').innerHTML = 
                    '<div class="no-results"><p>No places found for your search criteria.</p></div>';
                document.getElementById('loadMoreBtn').style.display = 'none';
            } else {
                displayPlaces(data.places);
                hasMore = data.has_more;
                nextPageToken = data.next_page_token || null;
                
                if (hasMore) {
                    document.getElementById('loadMoreBtn').style.display = 'block';
                } else {
                    document.getElementById('loadMoreBtn').style.display = 'none';
                }
            }
        } else {
            document.getElementById('errorMessage').textContent = data.message || 'An error occurred';
            document.getElementById('errorMessage').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('scrapeBtn').disabled = false;
        document.getElementById('errorMessage').textContent = 'An error occurred while scraping. Please try again.';
        document.getElementById('errorMessage').style.display = 'block';
    });
}

function displayPlaces(places) {
    const grid = document.getElementById('placesGrid');
    
    places.forEach(place => {
        const card = document.createElement('div');
        card.className = 'place-card';
        
        const image = place.main_photo || 'https://via.placeholder.com/300x200?text=No+Image';
        const rating = place.rating ? place.rating.toFixed(1) : 'N/A';
        const reviews = place.user_ratings_total || 0;
        
        card.innerHTML = `
            <img src="${image}" alt="${place.name}" class="place-card-image" onerror="this.src='https://via.placeholder.com/300x200?text=No+Image'">
            <div class="place-card-body">
                <h3 class="place-card-title">${place.name}</h3>
                <p class="place-card-address">${place.address || 'Address not available'}</p>
                <div class="place-card-rating">
                    <span class="rating-stars">${generateStars(place.rating)}</span>
                    <span class="rating-text">${rating} (${reviews} reviews)</span>
                </div>
                <div class="place-card-actions">
                    ${place.maps_link ? `<a href="${place.maps_link}" target="_blank" class="btn-view-maps">View on Google Maps</a>` : ''}
                </div>
            </div>
        `;
        
        grid.appendChild(card);
    });
}

function generateStars(rating) {
    if (!rating) return 'N/A';
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '★'.repeat(fullStars);
    if (hasHalfStar) stars += '½';
    return stars;
}

function loadMore() {
    currentPage++;
    document.getElementById('loadMoreBtn').disabled = true;
    document.getElementById('loadMoreBtn').textContent = 'Loading...';
    document.getElementById('progressText').textContent = `Loading page ${currentPage}...`;
    document.getElementById('loadingSpinner').style.display = 'block';
    
    fetch('{{ route("scraper.scrape") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            city: currentCity,
            category: currentCategory,
            page: currentPage,
            next_page_token: nextPageToken
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('loadMoreBtn').disabled = false;
        document.getElementById('loadMoreBtn').textContent = 'Load More';
        
        if (data.success && data.places.length > 0) {
            displayPlaces(data.places);
            hasMore = data.has_more;
            nextPageToken = data.next_page_token || null;
            
            if (!hasMore) {
                document.getElementById('loadMoreBtn').style.display = 'none';
            }
        } else {
            document.getElementById('loadMoreBtn').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('loadMoreBtn').disabled = false;
        document.getElementById('loadMoreBtn').textContent = 'Load More';
    });
}
</script>
@endsection

