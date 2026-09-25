<x-dashboard-layout :title="($record ? 'Edit ' : 'Add ').$label.' Record'">
<div class="pm-page mx-auto max-w-4xl space-y-5">
    <nav aria-label="Breadcrumb" class="text-sm text-slate-600"><a class="hover:underline" href="{{ route('monitoring.index', ['type' => $type]) }}">Monitoring Module</a> / {{ $label }}</nav>
    <header><h1 class="text-2xl font-bold">{{ $record ? 'Edit' : 'Add' }} {{ $label }} Record</h1><p class="mt-1 text-sm text-slate-600">{{ $type === 'materials' ? 'Record the current condition and maintenance dates for a project material.' : 'Record one entry per project and reporting period.' }}</p></header>
    @include('admin-pages.admin-project-management.partials.feedback')
    @if($projects->isEmpty())<p class="rounded-xl border border-amber-200 bg-amber-50 p-4">No active projects are available. An administrator must create a project and assign your association before you can record monitoring data.</p>@else
    <form data-monitoring-form method="POST" action="{{ $record ? route('monitoring.update', [$type, $record->id]) : route('monitoring.store', $type) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
        @csrf @if($record) @method('PUT') @endif
        <p class="text-xs text-slate-600">* Required field</p>
        <div class="grid gap-4 md:grid-cols-2">
            @php($projectOptions = $projects->mapWithKeys(fn($project) => [$project->id => $project->title.' — '.$project->association_name]))
            @if($record)
                <input type="hidden" name="project_id" value="{{ $record->project_id }}"><div class="md:col-span-2"><p class="pm-label">Project</p><p>{{ $record->project_title }} — {{ $record->association_name }}</p></div>
            @else
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'project_id','label'=>'Project','type'=>'select','value'=>null,'required'=>true,'options'=>$projectOptions,'span'=>'md:col-span-2'])
            @endif
            @if($type !== 'materials')
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'year','label'=>'Year','type'=>'number','value'=>$record?->year ?? now('Asia/Manila')->year,'required'=>true,'min'=>1900,'step'=>1])
                @if($type === 'production')
                    @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'quarter_id','label'=>'Quarter','type'=>'select','value'=>$record?->quarter_id,'required'=>true,'options'=>$quarters])
                    @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'target_output','label'=>'Target Output','type'=>'number','value'=>$record?->target_output,'required'=>true,'min'=>0,'step'=>'0.01','help'=>'Use the same unit for target and actual output. State the unit in remarks.'])
                    @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'actual_output','label'=>'Actual Output','type'=>'number','value'=>$record?->actual_output,'required'=>true,'min'=>0,'step'=>'0.01'])
                @else
                    @php($months = collect(range(1,12))->mapWithKeys(fn($month) => [$month => date('F', mktime(0,0,0,$month,1))]))
                    @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'month','label'=>'Month','type'=>'select','value'=>$record?->month,'required'=>true,'options'=>$months])
                    @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'gross_income','label'=>'Gross Income (₱)','type'=>'number','value'=>$record?->gross_income,'required'=>true,'min'=>0,'step'=>'0.01','help'=>'Total income before expenses.'])
                @endif
            @else
                <div><label class="pm-label" for="monitoring-material">Project Material *</label>
                    <select id="monitoring-material" name="project_material_id" class="pm-input" required aria-describedby="monitoring-material-error">
                        <option value="">Select a material</option>
                        @foreach($materials as $material)<option value="{{ $material->id }}" data-project="{{ $material->project_id }}" @selected(old('project_material_id', $record?->project_material_id) == $material->id)>{{ $material->item_name }} — {{ $projectOptions[$material->project_id] ?? '' }}</option>@endforeach
                    </select><p class="mt-1 text-xs text-slate-600">Choose a material from the selected project.</p><p id="monitoring-material-error" class="mt-1 text-sm text-red-800">{{ $errors->first('project_material_id') }}</p>
                </div>
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'condition_status_id','label'=>'Condition','type'=>'select','value'=>$record?->condition_status_id,'required'=>true,'options'=>$conditions])
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'material_description','label'=>'Material Description (optional)','value'=>$record?->material_description,'maxLength'=>255,'span'=>'md:col-span-2'])
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'scheduled_maintenance','label'=>'Scheduled Maintenance (optional)','type'=>'date','value'=>$record?->scheduled_maintenance])
                @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'actual_maintenance','label'=>'Actual Maintenance (optional)','type'=>'date','value'=>$record?->actual_maintenance,'help'=>'Leave blank until maintenance is completed.'])
            @endif
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'monitoring','name'=>'remarks','label'=>'Remarks (optional)','type'=>'textarea','value'=>$record?->remarks,'span'=>'md:col-span-2'])
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4"><a class="pm-action border border-slate-300" href="{{ route('monitoring.index', ['type'=>$type]) }}">Cancel</a><button class="pm-primary" type="submit">Save Record</button></div>
    </form>
    @endif
</div>
</x-dashboard-layout>
