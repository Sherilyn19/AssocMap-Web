<x-dashboard-layout :title="$training ? 'Edit Training' : 'Create Training'">
<div data-training-page class="pm-page mx-auto max-w-4xl space-y-5">
    <nav aria-label="Breadcrumb" class="text-sm text-slate-600"><a class="hover:underline" href="{{ route('trainings.index') }}">Training Management</a> / {{ $training ? 'Edit Training' : 'Create Training' }}</nav>
    <header><h1 class="text-2xl font-bold">{{ $training ? 'Edit Training' : 'Create Training' }}</h1><p class="mt-1 text-sm text-slate-600">Record the training details, then register participants and track attendance.</p></header>
    @include('admin-pages.admin-project-management.partials.feedback')
    <form method="POST" action="{{ $training ? route('trainings.update', $training) : route('trainings.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
        @csrf
        @if($training) @method('PUT') @endif
        <p class="text-xs text-slate-600">* Required field</p>
        {{-- Reuse the existing management field presentation and validation messages. --}}
        <div class="grid gap-4 md:grid-cols-2">
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'title','label'=>'Training Title','value'=>$training?->title,'required'=>true,'maxLength'=>255,'span'=>'md:col-span-2'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'association_id','label'=>'Association','type'=>'select','value'=>$training?->association_id,'required'=>true,'options'=>$associations->pluck('name','id'),'help'=>'Participants must belong to this association.'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'program_component_id','label'=>'Program Component','type'=>'select','value'=>$training?->program_component_id,'required'=>true,'options'=>$programComponents->pluck('name','id')])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'training_type','label'=>'Training Type','value'=>$training?->training_type,'required'=>true,'maxLength'=>100,'help'=>'For example: Skills Training, Orientation, or Workshop.'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'date_conducted','label'=>'Training Date','type'=>'date','value'=>$training?->date_conducted?->format('Y-m-d'),'required'=>true,'help'=>'Use the scheduled date for upcoming training or the actual date for past training.'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'venue','label'=>'Venue','value'=>$training?->venue,'required'=>true,'maxLength'=>255])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'conducted_by','label'=>'Conducted By','value'=>$training?->conducted_by,'required'=>true,'maxLength'=>255,'help'=>'Name of the facilitator or organizing office.'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'training_cost','label'=>'Training Cost (₱)','type'=>'number','value'=>$training?->training_cost,'required'=>true,'min'=>0,'step'=>'0.01','help'=>'Enter 0 when there is no recorded cost.'])
            @include('admin-pages.admin-project-management.partials.field', ['prefix'=>'training','name'=>'remarks','label'=>'Remarks (optional)','type'=>'textarea','value'=>$training?->remarks,'span'=>'md:col-span-2'])
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4"><a class="pm-action border border-slate-300" href="{{ $training ? route('trainings.show', $training) : route('trainings.index') }}">Cancel</a><button type="submit" class="pm-primary">{{ $training ? 'Save Changes' : 'Create Training' }}</button></div>
    </form>
</div>
</x-dashboard-layout>
