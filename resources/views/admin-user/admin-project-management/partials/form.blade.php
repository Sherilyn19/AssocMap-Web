{{-- Shared create/edit fields. Native forms POST; the method override selects the update route when editing. --}}
<form method="POST" action="{{ $action }}" data-pm-validate class="space-y-6 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
    @csrf @if($method !== 'POST') @method($method) @endif
    <p class="text-xs text-slate-600">* Required field</p>
    <fieldset>
<legend class="mb-4 font-semibold">Project Information</legend>
<div class="grid gap-4 md:grid-cols-2">
        @include('shared.partials.field', ['name'=>'title','label'=>'Project Title','value'=>$project?->title,'required'=>true,'maxLength'=>255,'span'=>'md:col-span-2'])
        @include('shared.partials.field', ['name'=>'association_id','label'=>'Association','type'=>'select','value'=>$project?->association_id,'required'=>true,'options'=>$associations->pluck('name','id')])
        @include('shared.partials.field', ['name'=>'program_component_id','label'=>'Program Component','type'=>'select','value'=>$project?->program_component_id,'required'=>true,'options'=>$programComponents->pluck('name','id'),'help'=>'Classification: Aquaculture, Capture Fisheries, or Post-Harvest.'])
        @include('shared.partials.field', ['name'=>'commodity_type','label'=>'Commodity Type','value'=>$project?->commodity_type,'required'=>true,'maxLength'=>255,'span'=>'md:col-span-2','help'=>'Actual fisheries/livelihood commodity, such as Bangus, Tilapia, or Seaweed.'])
    </div>
</fieldset>
    <fieldset class="border-t border-slate-200 pt-5">
<legend class="pr-3 font-semibold">Implementation</legend>
<div class="grid gap-4 md:grid-cols-2">
        @include('shared.partials.field', ['name'=>'implementation_date','label'=>'Implementation Date','type'=>'date','value'=>$project?->implementation_date?->format('Y-m-d'),'required'=>true])
        @include('shared.partials.field', ['name'=>'terminated_on','label'=>'Termination Date (if terminated)','type'=>'date','value'=>$project?->terminated_on?->format('Y-m-d'),'help'=>'Leave blank if the project has not been terminated. The project status remains separate.'])
        @include('shared.partials.field', ['name'=>'status_id','label'=>'Project Status','type'=>'select','value'=>$project?->status_id,'required'=>true,'options'=>$projectStatuses->pluck('status_name','id')])
    </div>
</fieldset>
    <fieldset class="border-t border-slate-200 pt-5">
<legend class="pr-3 font-semibold">Additional Information</legend>
        @include('shared.partials.field', ['name'=>'remarks','label'=>'Remarks (optional)','type'=>'textarea','value'=>$project?->remarks])
    </fieldset>
    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
<a class="pm-action border border-slate-300" href="{{ $project ? route('projects.show', $project) : route('projects.index') }}">Cancel</a>
<button class="pm-primary" type="submit">{{ $submitLabel }}</button>
</div>
</form>
