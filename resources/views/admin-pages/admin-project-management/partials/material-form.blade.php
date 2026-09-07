{{-- All editors use the same field names. Match the server-generated recovery marker before restoring old input or displaying errors. --}}
@php
    $prefix = $material ? 'material-'.$material->id : 'material-new';
    $recover = old('_material_form') === $prefix;
@endphp
<details id="{{ $prefix }}" data-pm-editor class="rounded-lg border border-slate-200 bg-white p-4" @if($recover) open @endif>
    <summary class="pm-action cursor-pointer font-semibold">{{ $material ? 'Edit: '.$material->item_name : '+ Add Material' }}</summary>
    <form method="POST" action="{{ $material ? route('projects.materials.update', [$project,$material]) : route('projects.materials.store',$project) }}" data-pm-validate data-pm-cost class="mt-4 space-y-4">
        @csrf @if($material) @method('PUT') @endif
        <input type="hidden" name="_material_form" value="{{ $prefix }}">
        <p class="text-xs text-slate-600">* Required field</p>
        <fieldset>
<legend class="mb-3 font-semibold">Material Information</legend>
<div class="grid gap-4 sm:grid-cols-2">
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'item_name','label'=>'Item Name','value'=>$material?->item_name,'required'=>true,'maxLength'=>255])
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'unit','label'=>'Unit','value'=>$material?->unit,'required'=>true,'maxLength'=>100])
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'quantity','label'=>'Quantity','type'=>'number','value'=>$material?->quantity,'required'=>true,'min'=>'0.01','step'=>'0.01'])
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'unit_cost','label'=>'Unit Cost (₱, optional)','type'=>'number','value'=>$material?->unit_cost,'min'=>0,'step'=>'0.01'])
        </div>
<p class="mt-3 text-sm">Calculated Total Cost: <output data-pm-cost-output class="font-semibold tabular-nums">{{ $material && $material->unit_cost !== null ? '₱'.number_format($material->total_cost,2) : 'Not recorded' }}</output>
</p>
</fieldset>
        <fieldset class="border-t border-slate-200 pt-4">
<legend class="pr-3 font-semibold">Status and Delivery</legend>
<div class="grid gap-4 sm:grid-cols-2">
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'status_id','label'=>'Material Status','type'=>'select','value'=>$material?->status_id,'required'=>true,'options'=>$materialStatuses->pluck('status_name','id')])
            @include('admin-pages.admin-project-management.partials.field', ['name'=>'delivery_date','label'=>'Delivery Date (optional)','type'=>'date','value'=>$material?->delivery_date?->format('Y-m-d')])
        </div>
</fieldset>
        <div class="flex flex-wrap justify-end gap-2">
<button class="pm-action border" type="button" data-pm-editor-cancel>Cancel</button>
<button class="pm-primary" type="submit">{{ $material ? 'Save Material' : 'Add Material' }}</button>
</div>
    </form>
</details>
