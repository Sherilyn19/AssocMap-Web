{{-- Service failures use flash messages; validation failures use the error bag. The focusable summary helps keyboard users find errors. --}}
@if(session('success'))
    <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div role="alert" tabindex="-1" data-pm-errors class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
        <p class="font-semibold">Please correct the highlighted information.</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
