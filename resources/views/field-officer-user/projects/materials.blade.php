{{-- Reuse the Area module's cards, table, and action colors. --}}
<section class="fo-table-card" data-project-materials>
    <header class="fo-section-heading">
        <div>
            <h3 class="font-semibold">Materials and delivery</h3>
            <p class="mt-1 text-sm text-slate-600">
                Record delivery dates for the listed materials.
            </p>
        </div>

                <div class="flex flex-wrap items-center gap-3">
            {{-- Keep the delivery summary visible beside the material filter. --}}
            <span class="fo-pill fo-pill-teal">
                {{ $project->materials->whereNotNull('delivery_date')->count() }}
                / {{ $project->materials->count() }} dates recorded
            </span>

            {{-- Filter the displayed rows without removing historical records. --}}
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <span>Show</span>

                <select
                    data-material-state
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                    <option value="all">All materials</option>
                    <option value="current">Current materials</option>
                    <option value="archived">Archived materials</option>
                </select>
            </label>
        </div>
    </header>

    <div class="overflow-x-auto"
         tabindex="0"
         role="region"
         aria-label="Project materials and delivery dates">
        <table class="fo-table">
            <caption class="sr-only">
                Materials for {{ $project->title }}
            </caption>

            <thead>
                <tr>
                    <th scope="col">Material / ID</th>
                    <th scope="col">Quantity</th>
                    <th scope="col">Unit cost (PHP)</th>
                    <th scope="col">Recorded status</th>
                    <th scope="col">Delivery date</th>
                </tr>
            </thead>

            <tbody>
                @forelse($project->materials as $material)
                    @php
                        // Restore only the row that failed validation.
                        $attempted = (string) old('_material_id')
                            === (string) $material->id;

                        $deliveryDate = $attempted
                            ? old('delivery_date')
                            : $material->delivery_date?->format('Y-m-d');

                        $deliveryDate = is_scalar($deliveryDate)
                            ? (string) $deliveryDate
                            : '';

                        $deliveryError = $attempted
                            ? $errors->first('delivery_date')
                            : '';

                        // Archived materials remain visible, but all write controls are removed.
                        $canRecordDelivery = !$project->is_archived
                            && !$project->association->is_archived
                            && $material->archived_at === null;
                    @endphp

                    {{-- Identify each material row so the dropdown can filter it. --}}
                    <tr
                        data-material-row
                        data-material-state="{{ $material->archived_at ? 'archived' : 'current' }}"
                    >
                        <th scope="row">
                            {{ $material->item_name }}
                            <span class="fo-record-id">
                                MATERIAL-{{ str_pad((string) $material->id, 6, '0', STR_PAD_LEFT) }}
                            </span>
                            @if($material->archived_at)
                                <span class="fo-pill fo-pill-amber mt-2">Archived</span>
                            @else
                                <span class="fo-pill fo-pill-slate mt-2">Current</span>
                            @endif
                        </th>

                        <td>
                            {{ $material->quantity }} {{ $material->unit }}
                        </td>

                        <td>
                            {{ $material->unit_cost !== null
                                ? number_format((float) $material->unit_cost, 2)
                                : 'Not recorded' }}
                        </td>

                        <td>
                            @include('shared.partials.badge', [
                                'label' => $material->status?->status_name
                                    ?? 'Not recorded',
                            ])
                        </td>

                        <td>
                            @if($canRecordDelivery)
                                {{-- Keep the existing authorized delivery endpoint. --}}
                                <form method="POST"
                                      action="{{ route('officer.projects.delivery', [$project, $material]) }}"
                                      class="space-y-2">
                                    @csrf
                                    @method('PATCH')

                                    <input type="hidden"
                                           name="_material_id"
                                           value="{{ $material->id }}">

                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="sr-only"
                                               for="delivery-date-{{ $material->id }}">
                                            Delivery date for {{ $material->item_name }}
                                        </label>

                                        <input id="delivery-date-{{ $material->id }}"
                                               type="date"
                                               name="delivery_date"
                                               value="{{ $deliveryDate }}"
                                               aria-invalid="{{ $deliveryError ? 'true' : 'false' }}"
                                               aria-describedby="delivery-error-{{ $material->id }}"
                                               class="rounded-lg border border-slate-300 p-2">

                                        {{-- The shared loading screen handles normal form submission. --}}
                                        <button type="submit"
                                                class="fo-action am-button-green">
                                            Save date
                                        </button>
                                        {{-- Edit and archive are available in the same material-management drawer. --}}
                                        <a href="{{ route('officer.projects.materials.edit', [$project, $material]) }}"
                                        data-project-manage
                                        data-editor-title="Manage material"
                                        class="fo-action am-button-warning">
                                            Manage
                                        </a>
                                    </div>

                                    <p id="delivery-error-{{ $material->id }}"
                                       class="text-sm text-red-800">
                                        {{ $deliveryError }}
                                    </p>
                                </form>
                            @else
                                {{-- Archived records remain visible without edit controls. --}}
                                {{ $material->delivery_date?->format('M d, Y')
                                    ?? 'Not recorded' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-500">
                            No materials recorded.
                        </td>
                    </tr>
                @endforelse

                    <tr data-material-filter-empty hidden>
                    <td colspan="5" class="text-center text-slate-500">
                        No materials match this record filter.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>