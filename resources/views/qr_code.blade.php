<!-- qr_code.blade.php -->
<html>
<head>
    <title> </title>
</head>
<body>
<img src="data:image/png;base64,' . base64_encode($qrCode) . '" alt="QR Code">
</body>
</html>
