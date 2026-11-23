<?php
    include("connect.php");
    include("login.php");
?>

<html>
    <head>
        <title>Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="style.css" />
        <style>
            .login-screen {
                min-height: 100vh;
                display: grid;
                grid-template-columns: 1.05fr 0.95fr;
            }

            .login-visual {
                position: relative;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                padding: 48px clamp(28px, 6vw, 84px);
                color: #fff;
                background: var(--primary);
            }

            .login-visual::before,
            .login-visual::after {
                content: "";
                position: absolute;
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 50%;
                width: 520px;
                height: 520px;
                right: -190px;
                bottom: -220px;
            }

            .login-visual::after {
                width: 360px;
                height: 360px;
                right: -105px;
                bottom: -135px;
            }

            .login-logo {
                position: relative;
                z-index: 1;
                display: flex;
                align-items: center;
                gap: 14px;
            }

            .brand-mark {
                width: 65px;
                height: 65px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            #logo-img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                mix-blend-mode: multiply;
            }

            .brand-name {
                font-size: 30px;
                font-weight: 700;
                letter-spacing: 0.2px;
            }

            .brand-subtitle {
                margin-top: 3px;
                font-size: 12px;
                opacity: 0.9;
            }

            .login-message {
                position: relative;
                z-index: 1;
                max-width: 560px;
                margin: 50px 0;
            }

            .login-message h1 {
                margin: 0 0 18px;
                font-size: clamp(32px, 4vw, 52px);
                line-height: 1.12;
            }

            .login-message p {
                font-size: 17px;
                line-height: 1.7;
                opacity: 0.9;
            }

            .login-side {
                display: grid;
                place-items: center;
                padding: 36px 22px;
            }

            .login-card {
                width: min(100%, 430px);
                padding: clamp(28px, 4vw, 44px);
                background: #fff;
                border-radius: 18px;
                box-shadow: 0 16px 45px rgba(24, 57, 105, 0.13);
            }

            .login-card h2 {
                margin: 0 0 8px;
                color: var(--text);
                font-size: 29px;
            }

            .login-card .intro {
                margin: 0 0 28px;
                color: var(--muted);
                line-height: 1.5;
                font-size: 12px;
            }

            .field {
                margin: 17px 0;
            }

            .field label {
                display: block;
                margin-bottom: 8px;
                color: #34445F;
                font-size: 13px;
                font-weight: 700;
            }

            .field input {
                width: 100%;
                padding: 14px 13px;
                border: 1px solid #D9E1ED;
                border-radius: 9px;
                outline: none;
                font-size: 15px;
            }

            .field input:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(63, 104, 110, 0.14);
            }

            .login-submit {
                width: 100%;
                margin-top: 12px;
                padding: 14px;
                border: 0;
                border-radius: 9px;
                color: #fff;
                background: linear-gradient(110deg, var(--primary-dark), var(--primary));
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                transition: filter 0.2s ease;
            }

            .login-submit:hover {
                filter: brightness(1.08);
            }

            @media (max-width: 760px) {
                .login-screen {
                    grid-template-columns: 1fr;
                }

                .login-visual {
                    min-height: 235px;
                    padding: 25px 24px;
                }

                .login-message {
                    margin: 28px 0 0;
                }

                .login-message h1 {
                    font-size: 30px;
                }

                .login-message p {
                    margin-bottom: 0;
                    font-size: 14px;
                }

                .login-train {
                    display: none;
                }

                .brand-name {
                    font-size: 23px;
                }

                .brand-mark {
                    font-size: 38px;
                }

                .login-side {
                    padding: 28px 18px;
                }
            }
  </style>
    </head>

    <body>
        <section class="login-screen" id="loginScreen">
            <div class="login-visual">
                <div class="login-logo"><div class="brand-mark">
                    <img id="logo-img" src="image/logo ka.png" alt="Logo">
                </div>
                <div>
                    <div class="brand-name">RailManage</div>
                    <div class="brand-subtitle">Manajemen Basis Data Penumpang dan Transaksi Kereta Api </div>
                </div>
            </div>
                <div class="login-message">
                    <h1>Selamat Datang di RailManage</h1>
                    <p>Kelola data penumpang, reservasi tiket, dan transaksi pembayaran melalui satu dashboard terintegrasi.</p>
                </div>
            </div>
            <div class = "login-side">
                <form class = "login-card" id="loginForm" name="form" action="login.php" method="POST" onsubmit="return isvalid()">
                    <h2>Masuk</h2>
                    <p class="intro">Silakan masuk menggunakan akun administrator Anda.</p>
                    <div class="field">
                        <label>Username: </label>
                        <input type="text" id="user" name="user" placeholder="Masukkan username" autocomplete="username" required>
                    </div>
                    <div class="field">
                    <label>Password: </label>
                    <input type="password" id="pass" name="pass" placeholder="Masukkan password" autocomplete="password" required>
                    </div>
                    <button class="login-submit" type="submit" value="Login" name = "submit">Masuk ke Dashboard&nbsp; →</button>
                </form>
            </div>
        </section>
        
    </body>
</html>