@extends(backpack_view('blank'))
@section('after_styles')
    <link href="{{ mix('/css/app.css') }}" rel="stylesheet">
    <script src="{{ mix('/js/app.js') }}" defer></script>
    <style>
        body{
            font-size: 13px !important;
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
@endsection
@section('content')
    @inertia
@endsection
