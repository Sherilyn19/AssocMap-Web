<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $heading ?? 'User Management unavailable' }}</title></head>
{{-- This page avoids the dashboard's database lookups when recovering from an error. --}}
<body><main><h1>{{ $heading ?? 'User Management is temporarily unavailable' }}</h1>
<p role="alert">{{ $message ?? 'We could not confirm the request. Check the account records before submitting another change.' }}</p>
<p><a href="{{ route('users.index') }}">Return to User Management</a></p></main></body>
</html>
