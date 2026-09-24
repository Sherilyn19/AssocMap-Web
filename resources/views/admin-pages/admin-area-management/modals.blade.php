    {{-- Defense: only the body scrolls; the heading and action footer stay reachable.
         Dynamic viewport sizing also accounts for mobile browser chrome. --}}
    <dialog id="am-municipality-modal" aria-labelledby="am-municipality-modal-title" class="am-area-dialog m-auto max-w-lg rounded-2xl p-0 backdrop:bg-black/50">
        <div class="am-area-dialog-panel bg-white shadow-card">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-assocmap-primary">Municipality Record</p>
                <h3 id="am-municipality-modal-title" class="text-lg font-bold text-assocmap-text">Add Municipality</h3>
            </div>

            <form id="am-municipality-form" class="am-area-dialog-form" method="POST" action="{{ route('areas.municipalities.store') }}">
                @csrf
                <div class="am-area-dialog-scroll">
                    @if (session('error') && ($recovery['entity'] ?? null) === 'municipality')
                        <p data-area-errors role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</p>
                    @endif
                    @if ($errors->getBag('municipality')->any())
                        <ul data-area-errors role="alert" class="mb-4 list-disc rounded-lg bg-red-50 p-4 pl-8 text-sm text-red-700">
                            @foreach ($errors->getBag('municipality')->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                    <input type="hidden" id="am-municipality-form-method" name="_method" value="POST">

                    <div class="space-y-4">
                        <label for="am-municipality-name" class="block text-sm font-medium">Municipality Name <span aria-hidden="true">*</span></label>
                        <input id="am-municipality-name" name="name" required maxlength="255" aria-describedby="am-municipality-name-preview" class="w-full rounded-lg border border-assocmap-border px-4 py-2.5 text-sm">
                        <p id="am-municipality-name-preview" data-area-value-preview="am-municipality-name" hidden class="am-area-name text-xs text-assocmap-secondary"></p>

                        <div class="flex flex-col gap-1.5">
                            <label for="am-municipality-address" class="text-sm font-medium text-assocmap-text">Address / Description</label>
                            <textarea id="am-municipality-address" name="address" rows="3" maxlength="500"
                                      class="w-full rounded-lg border border-assocmap-border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary"></textarea>
                            @error('address', 'municipality')
                                <p data-area-errors class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="am-municipality-province" class="text-sm font-medium text-assocmap-text">Province</label>
                            <input id="am-municipality-province" type="text" value="Cebu" disabled
                                   class="w-full rounded-lg border border-assocmap-border bg-assocmap-bg px-4 py-2.5 text-sm text-assocmap-secondary">
                        </div>
                    </div>

                </div>
                <div class="am-area-dialog-actions">
                    <button type="button" data-municipality-modal-close
                            class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                        Cancel
                    </button>
                    <x-primary-button type="submit" class="w-auto px-6">Save</x-primary-button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- Barangay create/edit modal --}}
    <dialog id="am-barangay-modal" aria-labelledby="am-barangay-modal-title" class="am-area-dialog m-auto max-w-lg rounded-2xl p-0 backdrop:bg-black/50">
        <div class="am-area-dialog-panel bg-white shadow-card">
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-assocmap-primary">Barangay Record</p>
                <h3 id="am-barangay-modal-title" class="text-lg font-bold text-assocmap-text">Add Barangay</h3>
            </div>

            <form id="am-barangay-form" class="am-area-dialog-form" method="POST" action="{{ route('areas.barangays.store') }}">
                @csrf
                <div class="am-area-dialog-scroll">
                    @if (session('error') && ($recovery['entity'] ?? null) === 'barangay')
                        <p data-area-errors role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</p>
                    @endif
                    @if ($errors->getBag('barangay')->any())
                        <ul data-area-errors role="alert" class="mb-4 list-disc rounded-lg bg-red-50 p-4 pl-8 text-sm text-red-700">
                            @foreach ($errors->getBag('barangay')->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                    <input type="hidden" id="am-barangay-form-method" name="_method" value="POST">

                    <div class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="am-barangay-area-unit" class="text-sm font-medium text-assocmap-text">
                                Municipality <span class="text-red-500 ml-0.5">*</span>
                            </label>
                            <select id="am-barangay-area-unit" name="area_unit_id" required aria-describedby="am-barangay-parent-preview"
                                    class="w-full rounded-lg border border-assocmap-border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                                <option value="">Select a municipality</option>
                                @foreach ($activeMunicipalities as $muniOption)
                                    <option value="{{ $muniOption->id }}">{{ $muniOption->name }}</option>
                                @endforeach
                            </select>
                            <p id="am-barangay-parent-preview" data-area-value-preview="am-barangay-area-unit" hidden class="am-area-name text-xs text-assocmap-secondary"></p>
                            @error('area_unit_id', 'barangay')
                                <p data-area-errors class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <label for="am-barangay-name" class="block text-sm font-medium">Barangay Name <span aria-hidden="true">*</span></label>
                        <input id="am-barangay-name" name="name" required maxlength="255" aria-describedby="am-barangay-name-preview" class="w-full rounded-lg border border-assocmap-border px-4 py-2.5 text-sm">
                        <p id="am-barangay-name-preview" data-area-value-preview="am-barangay-name" hidden class="am-area-name text-xs text-assocmap-secondary"></p>
                    </div>

                </div>
                <div class="am-area-dialog-actions">
                    <button type="button" data-barangay-modal-close
                            class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                        Cancel
                    </button>
                    <x-primary-button type="submit" class="w-auto px-6">Save</x-primary-button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- Read-only View Details modal --}}
    <dialog id="am-view-modal" aria-labelledby="am-view-title" class="am-area-dialog m-auto max-w-2xl rounded-2xl p-0 backdrop:bg-black/50">
        <div class="am-area-dialog-panel bg-white shadow-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-assocmap-primary">View Area Details</p>
                    <h3 id="am-view-title" tabindex="-1" autofocus class="am-area-name mt-1 text-xl font-bold text-assocmap-text">Area Details</h3>
                    <p id="am-view-subtitle" class="mt-1 text-sm text-assocmap-secondary"></p>
                </div>
                <span id="am-view-status" class="hidden"></span>
            </div>

            <div id="am-view-body" aria-live="polite" class="am-area-dialog-scroll mt-5"></div>

            <div class="am-area-dialog-actions">
                <button type="button" data-view-modal-close
                        class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                    Close
                </button>
            </div>
        </div>
    </dialog>

    {{-- Shared confirmation dialog (management-actions.js) --}}
    <dialog id="am-confirm-modal" aria-labelledby="am-confirm-title" aria-describedby="am-confirm-message" class="am-area-dialog m-auto max-w-sm rounded-2xl p-0 backdrop:bg-black/50">
        <div class="am-area-dialog-panel bg-white shadow-card">
            <h3 id="am-confirm-title" class="text-lg font-bold text-assocmap-text">Are you sure?</h3>
            <p id="am-confirm-message" class="am-area-dialog-scroll mt-2 text-sm text-assocmap-secondary"></p>
            <div class="am-area-dialog-actions">
                <button type="button" data-confirm-close autofocus
                        class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                    Cancel
                </button>
                <button type="button" id="am-confirm-action-btn"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Confirm
                </button>
            </div>
        </div>
    </dialog>
