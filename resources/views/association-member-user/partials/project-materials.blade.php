    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold">Project materials</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($project->materials as $material)
                <li class="grid gap-4 py-5 sm:grid-cols-2 lg:grid-cols-4"><div><h3 class="break-words font-semibold">{{ $material->item_name }}</h3><div class="mt-2">@include('shared.partials.badge', ['label' => $material->status?->status_name])</div></div><div><p class="text-sm text-slate-500">Quantity</p><p class="mt-1">{{ number_format($material->quantity, 2) }} {{ $material->unit }}</p></div><div><p class="text-sm text-slate-500">Unit cost / calculated total</p><p class="mt-1">{{ $material->unit_cost !== null ? '₱'.number_format($material->unit_cost, 2).' / ₱'.number_format($material->quantity * $material->unit_cost, 2) : 'Not recorded' }}</p></div><div><p class="text-sm text-slate-500">Delivery date</p><p class="mt-1">{{ $material->delivery_date?->format('M j, Y') ?? 'Not recorded' }}</p></div></li>
            @empty <li class="py-8 text-sm text-slate-500">No materials have been recorded for this project yet.</li> @endforelse
        </ul>
    </section>

