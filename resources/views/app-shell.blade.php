@extends('layouts.app')

@section('content')
    <div id="react-app" data-user-id="{{ auth()->id() }}"></div>
    @vite('resources/js-app/main.jsx')
@endsection
