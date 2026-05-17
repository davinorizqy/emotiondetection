<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMOSCAN</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body>

    <div class="simple-login-container">

        <div class="simple-login-content">

            <!-- LOGO -->
            <div class="simple-logo">

                <h1>EMOSCAN</h1>
                <p>EMOTIONAL DETECTION & ANALYTICS</p>

            </div>

            <!-- CARD -->
            <div class="simple-login-card">

                <form method="POST" action="/login">
                    @csrf

                    <!-- 🔥 NOTIF ERROR -->
                    @if(session('error'))
                        <div style="
                            background-color: #ff4d4f;
                            color: white;
                            padding: 10px;
                            border-radius: 8px;
                            margin-bottom: 15px;
                            text-align: center;
                        ">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- EMAIL -->
                    <div class="simple-input-group">
                        <label>EMAIL</label>
                        <div class="simple-input-wrapper">
                            <span>✉</span>
                            <input type="email" name="email" placeholder="operator@democa.ai">
                        </div>
                    </div>

                    <!-- PASSWORD -->
                    <div class="simple-input-group">
                        <label>PASSWORD</label>
                        <div class="simple-input-wrapper">
                            <span>🔒</span>
                            <input type="password" name="password" placeholder="••••••••">
                        </div>
                    </div>

                    <!-- BUTTON -->
                    <button type="submit" class="simple-login-btn">
                        Login →
                    </button>

                </form>

            </div>

        </div>

    </div>

</body>

</html>
<script>
    function goDashboard() {
        window.location.href = "/dashboard";
    }
</script>