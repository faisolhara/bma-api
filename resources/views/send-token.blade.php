<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<body>
	Yth.  {{ $to }} 
	<br>
	Kami menerima notifikasi untuk reset password sistem Building Management (BMA). 
	<br>
	Untuk melanjutkan reset password gunakan kode verifikasi berikut.
	<br>
	<br>
	<br>
	=============================================================
	<h3>Kode Verifikasi <strong>{{ $reset_token }}</strong></h3>
	=============================================================
	<br>
	<br>
	<br>
	Kode verifikasi valid sampai {{ $expire_reset_token }}
	<br>
	Abaikan email ini jika anda tidak merasa ingin mereset password.
	Dan ubahlah password anda secara periodik untuk peningkatan keamanan akun anda.
	<br>
	Terima kasih.
	<br>
	<br>
	<strong>Building Management &copy; DCK 2017 </strong>
</body>
</html>