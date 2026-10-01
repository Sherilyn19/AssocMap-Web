@php($editable = ! $training->is_archived && ! $training->association->is_archived)
<div class="space-y-6">
@include('shared.trainings.details', ['readOnly' => false])
{{-- Keep existing validated forms and attendance permissions in the officer workspace. --}}
<details class="am-training-card" @if($errors->any() || request()->has('page') || session()->has('success')) open @endif>
    <summary class="am-training-card__summary font-semibold">Update information and manage attendance</summary>
    <div class="am-training-card__body space-y-6">@include('field-officer-user.trainings.manage')</div>
</details>
</div>
