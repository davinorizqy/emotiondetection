<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>EMOSCAN</title>

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>

    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <h1>EMOSCAN</h1>
                <span>EMOTION MONITORING</span>
            </div>

            <div class="menu">
                <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                    Dashboard
                </a>

                <a href="/analytics" class="{{ request()->is('analytics') ? 'active' : '' }}">
                    Analytics
                </a>
            </div>
        </div>

        <div class="profile" onclick="toggleMenu()">
            <span>Admin</span>
            <img src="https://i.pravatar.cc/40" alt="Profile">

            <div class="dropdown" id="profileMenu">
                <form method="GET" action="/login">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- INI PENTING BANGET -->
    @yield('content')


</body>

</html>
<script>
    function toggleMenu() {
        const menu = document.getElementById('profileMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>

    <!-- OpenCV -->
    <script src="https://docs.opencv.org/4.x/opencv.js"></script>

    <!-- JS -->
    <script src="{{ asset('script.js') }}"></script>