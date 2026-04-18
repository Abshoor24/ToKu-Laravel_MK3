<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>OTP</title>
</head>

<body style="font-family: Arial; background: #f4f4f4; padding: 20px;">

    <div
        style="max-width: 500px; margin: auto; background: white; padding: 20px; border-radius: 10px; text-align: center;">

        <!-- Gambar -->
        <img src="https://ibb.co.com/bjBfxMhv" width="80" />
        <h2 style="color: #333;">Verifikasi Akun Kamu</h2>

        <p style="color: #555;">
            Gunakan kode OTP berikut untuk verifikasi akun kamu:
        </p>

        <!-- OTP BOX -->
        <div style="font-size: 30px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; color: #2d89ef;">
            {{ $otp }}
        </div>

        <p style="color: #999; font-size: 12px;">
            Kode ini berlaku selama 5 menit.
        </p>

        <hr>

        <p style="font-size: 12px; color: #aaa;">
            Jika kamu tidak meminta kode ini, abaikan email ini.
        </p>

    </div>

</body>

</html>
