<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Wiseelik')</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1e1e2f;
            color: #ffffff;
        }
        .nav-container {
            padding: 1rem 2rem;
        }
        .logo a {
            font-size: 24px;
            font-weight: bold;
            color: #ffffff;
            text-decoration: none;
        }
        .logo img {
            max-width: 130px;
        }
        .nav-links a {
            color: #ffffff;
            margin-left: 1.5rem;
            text-decoration: none;
        }
        footer {
            background-color: #1e1e2f;
            padding: 2rem 0;
        }
        .footer-logo img {
            max-width: 100px;
        }
        .footer-links a {
            color: #ffffff;
            margin-left: 1.5rem;
            text-decoration: none;
        }
        
        /* Mobile drawer styles */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .mobile-drawer {
            position: fixed;
            top: 0;
            right: -250px;
            width: 250px;
            height: 100%;
            background-color: #252540;
            transition: right 0.3s ease;
            z-index: 1000;
            padding: 20px;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2);
            overflow-y: auto;
        }
        
        .mobile-drawer.open {
            right: 0;
        }
        
        .mobile-drawer .close-drawer {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
        }
        
        .mobile-drawer-links {
            margin-top: 50px;
            display: flex;
            flex-direction: column;
        }
        
        .mobile-drawer-links a {
            color: white;
            padding: 12px 0;
            text-decoration: none;
            font-size: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }
        
        .overlay.open {
            display: block;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .footer-logo, .footer-links {
                text-align: center !important;
            }
            
            .footer-links {
                margin-top: 1.5rem;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .footer-links a {
                margin: 0.5rem 0;
                display: block;
            }
        }
    </style>
    @yield('styles')
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <div class="mobile-drawer" id="mobileDrawer">
        <button class="close-drawer" id="closeDrawer">&times;</button>
        <div class="mobile-drawer-links">
            <a href="#">About Us</a>
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            <a href="#">Download</a>
        </div>
    </div>

    <header>
        <nav class="nav-container d-flex justify-content-between align-items-center">
            <div class="logo">
                <a href="{{ route('home') }}">
                    <img src="{{ asset('images/wiseelik-logo-mascot.png') }}" alt="Download on App Store">
                </a>
            </div>
            <div class="nav-links">
                <a href="#">About Us</a>
                <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
                <a href="#">Download</a>
            </div>
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                </svg>
            </button>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <div class="container footer-container">
            <div class="row align-items-center">
                <div class="col-md-6 footer-logo d-flex flex-column flex-md-row align-items-center">
                    <img src="{{ asset('images/wiseelik-logo-mascot.png') }}" alt="Wiseelik Logo" class="me-md-3 mb-2 mb-md-0">
                    <p class="mb-0">© 2023 WISEELIK. All rights reserved.</p>
                </div>
                <div class="col-md-6 footer-links text-md-end mt-3 mt-md-0">
                    <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
                    <a href="{{ route('terms-of-service') }}">Terms & Condition</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const closeDrawer = document.getElementById('closeDrawer');
            const mobileDrawer = document.getElementById('mobileDrawer');
            const overlay = document.getElementById('overlay');
            
            // Open mobile drawer
            mobileMenuToggle.addEventListener('click', function() {
                mobileDrawer.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            
            // Close mobile drawer
            function closeDrawerFunc() {
                mobileDrawer.classList.remove('open');
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
            
            closeDrawer.addEventListener('click', closeDrawerFunc);
            overlay.addEventListener('click', closeDrawerFunc);
        });
    </script>
    @yield('scripts')
</body>
</html>