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
