<x-dashboard-layout :title="$training ? 'Edit Training' : 'Create Training'">
<div data-training-page class="pm-page mx-auto max-w-4xl space-y-5">
    <nav aria-label="Breadcrumb" class="text-sm text-slate-600"><a class="hover:underline" href="{{ route('trainings.index') }}">Training Management</a> / {{ $training ? 'Edit Training' : 'Create Training' }}</nav>
    <header><h1 class="text-2xl font-bold">{{ $training ? 'Edit Training' : 'Create Training' }}</h1><p class="mt-1 text-sm text-slate-600">Record the training details, then register participants and track attendance.</p></header>
    @include('shared.partials.feedback')
    <form method="POST" action="{{ $training ? route('trainings.update', $training) : route('trainings.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
        @csrf
        @if($training) @method('PUT') @endif
        <p class="text-xs text-slate-600">* Required field</p>
        {{-- Reuse the existing management field presentation and validation messages. --}}
        <div class="grid gap-4 md:grid-cols-2">
            @include('shared.partials.field', ['prefix'=>'training','name'=>'title','label'=>'Training Title','value'=>$training?->title,'required'=>true,'maxLength'=>255,'span'=>'md:col-span-2'])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'association_id','label'=>'Association','type'=>'select','value'=>$training?->association_id,'required'=>true,'options'=>$associations->pluck('name','id'),'help'=>'Participants must belong to this association.'])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'program_component_id','label'=>'Program Component','type'=>'select','value'=>$training?->program_component_id,'required'=>true,'options'=>$programComponents->pluck('name','id')])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'training_type','label'=>'Training Type','value'=>$training?->training_type,'required'=>true,'maxLength'=>100,'help'=>'For example: Skills Training, Orientation, or Workshop.'])
            @if($training)
                <div><span class="pm-label">From Date (read-only)</span><p>{{ $training->date_conducted?->format('M d, Y') ?? 'Not recorded' }}</p></div>
                <div><span class="pm-label">To Date (read-only)</span><p>{{ $training->end_date?->format('M d, Y') ?? 'Not recorded' }}</p></div>
            @else
                @include('shared.partials.field', ['prefix'=>'training','name'=>'date_conducted','label'=>'From Date','type'=>'date','required'=>true])
                @include('shared.partials.field', ['prefix'=>'training','name'=>'end_date','label'=>'To Date','type'=>'date','required'=>true])
            @endif
            @include('shared.partials.field', ['prefix'=>'training','name'=>'stage','label'=>'Training Stage','type'=>'select','value'=>$training ? $training->stage : 'proposal','required'=>true,'options'=>\App\Models\Training::STAGES])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'venue','label'=>'Venue','value'=>$training?->venue,'required'=>true,'maxLength'=>255])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'conducted_by','label'=>'Conducted By','value'=>$training?->conducted_by,'required'=>true,'maxLength'=>255,'help'=>'Name of the facilitator or organizing office.'])
            @include('shared.partials.field', ['prefix'=>'training','name'=>'remarks','label'=>'Remarks (optional)','type'=>'textarea','value'=>$training?->remarks,'span'=>'md:col-span-2'])
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4"><a class="pm-action border border-slate-300" href="{{ $training ? route('trainings.show', $training) : route('trainings.index') }}">Cancel</a><button type="submit" class="pm-primary">{{ $training ? 'Save Changes' : 'Create Training' }}</button></div>
    </form>
</div>
</x-dashboard-layout>
