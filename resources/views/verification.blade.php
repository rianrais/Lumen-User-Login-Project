<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up Confirmation</title>
</head>
<body>
    <h1>Terimakasih telah mendaftar !</h1>

    <p>
        Klik link berikut untuk mengaktifkan akun anda: <a href='{{ url('register/verification/' . $email_token) }}'>link verifikasi</a>
    </p>
</body>
</html>
