@extends('layouts.user')

@section('title', 'Need a Garage? | Wiseelik')

@section('styles')
<style>
    .hero-content h1 {
        font-size: 2.2rem;
        font-weight: bold;
        margin-bottom: 0.25rem;
    }
    .hero-content p {
        font-size: 1.1rem;
        margin-bottom: 1.25rem;
    }
    .app-download-buttons img {
        height: 35px;
        margin-right: 8px;
    }
    .hero-section {
        padding: 2rem 0;
        position: relative;
        overflow: visible;
        margin-bottom: 60px;
    }
    .hero-image {
        position: relative;
    }
    .hero-image img {
        max-width: 90%;
        position: absolute;
        bottom: -300px;
        right: 10%;
    }
    .app-screenshot img {
        max-width: 100%;
        border-radius: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    h2 {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 1.5rem;
    }
    .bg-darker {
        background-color: #171727;
    }
    .connect-with-us {
        position: relative;
        overflow: hidden;
    }
    .connect-with-us .app-screenshot img {
        transform: rotate(5deg);
    }
    
    /* Added responsive styling */
    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 1.8rem;
            text-align: center;
        }
        .hero-content p {
            font-size: 1rem;
            text-align: center;
        }
        .hero-image img {
            margin-top: 2rem;
            position: static;
            max-width: 100%;
            bottom: auto;
            right: auto;
        }
        .app-download-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        .app-download-buttons a {
            margin-bottom: 10px;
        }
        .hero-section {
            margin-bottom: 20px;
            padding-bottom: 0;
        }
        h2 {
            font-size: 1.7rem;
            text-align: center;
        }
        .section-content p {
            text-align: center;
        }
        .section-content .app-download-buttons {
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')
    <!-- Hero Section -->
     @if(session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif
    <div class="container hero-section">
        <div class="row align-items-center">
            <div class="col-md-6 hero-content">
                <h1>Need a Garage?</h1>
                <p>We'll Help You Find It!</p>
                <div class="app-download-buttons">
                    <a href="#" class="app-store-button">
                        <img src="{{ asset('images/app-store-badge.png') }}" alt="Download on App Store">
                    </a>
                    <a href="#" class="google-play-button">
                        <img src="{{ asset('images/google-play-badge.png') }}" alt="Get it on Google Play">
                    </a>
                </div>
            </div>
            <div class="col-md-6 hero-image text-center">
                <img src="{{ asset('images/car-hero.png') }}" alt="Car Image" class="img-fluid">
            </div>
        </div>
    </div>

    <!-- Who We Are Section -->
    <section class="who-we-are bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 app-screenshot mb-4 mb-md-0 order-md-1 order-2">
                    <img src="{{ asset('images/app-screenshot.png') }}" alt="Wiseelik App" class="img-fluid">
                </div>
                <div class="col-md-4 text-black section-content order-md-2 order-1 mb-4 mb-md-0">
                    <h2>Who we are</h2>
                    <p>Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. Lorem ipsum is simply dummy text of the printing and typesetting industry.Lorem ipsum is simply dummy text of the printing and typesetting industry.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Bookings and Services Section -->
    <section class="bookings-services py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 section-content mb-4 mb-md-0">
                    <h2>Bookings and Services</h2>
                    <p>Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. Lorem ipsum is simply dummy text of the printing and typesetting.</p>
                </div>
                <div class="col-md-8 app-screenshot text-center">
                    <img src="{{ asset('images/booking-screenshot.png') }}" alt="Booking Screenshot" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Connect With Us Section -->
    <section class="connect-with-us bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 section-content text-dark mb-4 mb-md-0 order-md-1 order-2">
                    <h2>Connect with us</h2>
                    <p>Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum is simply dummy text of the printing and typesetting industry. Lorem ipsum is simply dummy text of the printing and typesetting industry.</p>
                    <div class="app-download-buttons mt-4">
                        <a href="#" class="app-store-button">
                            <img src="{{ asset('images/app-store-badge.png') }}" alt="Download on App Store">
                        </a>
                        <a href="#" class="google-play-button">
                            <img src="{{ asset('images/google-play-badge.png') }}" alt="Get it on Google Play">
                        </a>
                    </div>
                </div>
                <div class="col-md-6 app-screenshot text-center order-md-2 order-1 mb-4 mb-md-0">
                    <img src="{{ asset('images/connect-screenshot.png') }}" alt="Connect Screenshot" class="img-fluid">
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
    // You can add page-specific scripts here
</script>
@endsection