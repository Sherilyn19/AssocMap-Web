{{-- Shared feedback: secrets are never included in old input or error messages. --}}
@if (session('success'))
    <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">
        <p class="font-semibold">Please correct the following:</p>
        <ul class="ml-5 list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
