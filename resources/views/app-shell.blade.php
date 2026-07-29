<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SteelERP</title>
    @vite(['resources/css/app.css', 'resources/js-app/main.jsx'])
</head>
<body>
    <div id="react-app" data-user-id="{{ auth()->id() }}"></div>
</body>
</html>
