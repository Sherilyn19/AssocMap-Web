<x-dashboard-layout title="Submit Membership Application">
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <a class="text-blue-800 underline" href="{{ route('membership.index') }}">Back to Member Management</a>
    <header><h1 class="text-2xl font-bold">Submit Membership Application</h1><p class="mt-2 text-slate-600">{{ $association->name }} · Submission creates a Pending application. It does not add an official member.</p></header>
    @include('membership.partials.feedback')
    <form method="POST" action="{{ route('membership.applications.store') }}" class="space-y-6 rounded-xl border bg-white p-6">
        @csrf
        {{-- The association is derived from login; there is deliberately no association selector. --}}
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ([['first_name','First name',true], ['middle_name','Middle name',false], ['last_name','Last name',true], ['birthday','Birthday',true], ['beneficiary_type','Beneficiary type',false], ['contact_number','Contact number',false]] as [$field,$label,$required])
                <label><span class="block text-sm font-semibold">{{ $label }}{{ $required ? ' *' : '' }}</span>
                    <input name="{{ $field }}" type="{{ $field === 'birthday' ? 'date' : 'text' }}" value="{{ is_scalar(old($field)) ? old($field) : '' }}" @required($required) @if($field === 'birthday') max="{{ now()->toDateString() }}" @endif maxlength="{{ $field === 'contact_number' ? 50 : ($field === 'beneficiary_type' ? 100 : 255) }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="error-{{ $field }}" class="mt-1 w-full rounded-lg border border-slate-300 p-3">
                    <span id="error-{{ $field }}" class="text-sm text-red-700">@error($field){{ $message }}@enderror</span>
                </label>
            @endforeach
            <label><span class="block text-sm font-semibold">Sex *</span><select name="sex_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-3"><option value="">Select sex</option>@foreach($sexOptions as $sex)<option value="{{ $sex->id }}" @selected(old('sex_id') == $sex->id)>{{ $sex->sex_name }}</option>@endforeach</select>@error('sex_id')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
            <label class="sm:col-span-2"><span class="block text-sm font-semibold">Address</span><textarea name="address" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-3">{{ is_string(old('address')) ? old('address') : '' }}</textarea>@error('address')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        </div>
        <button class="rounded-lg bg-slate-800 px-5 py-3 font-semibold text-white">Submit for Review</button>
    </form>
</div>
</x-dashboard-layout>
