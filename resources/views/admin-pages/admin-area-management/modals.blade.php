{{-- Use the same styles for all Area dialogs. These styles apply only to this module.
     Dialogs keep keyboard focus inside and support Escape to close.
     Only the content scrolls, so headings and action buttons stay visible. --}}
@php
    // Override the old Area padding locally so headers, scroll bodies and footers
    // each own their spacing; the existing height and scrolling rules still apply.
    $areaModalFieldClass = 'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';
    $areaModalLabelClass = 'text-sm font-medium text-slate-700';
    $areaModalButtonClass = 'inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
    $areaModalSecondaryClass = $areaModalButtonClass . ' border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-400';
    $areaModalPrimaryClass = $areaModalButtonClass . ' bg-slate-800 text-white hover:bg-slate-700 focus:ring-slate-400';
    $areaModalFooterClass = 'am-area-dialog-actions border-t border-slate-200 !bg-slate-50 !px-5 !py-4 sm:!px-6';
@endphp
{{-- Match dialog headings and borders to the other modules.
         Keep the heading IDs so JavaScript can update titles and screen readers
         can identify each dialog. --}}
    <dialog id="am-municipality-modal" aria-labelledby="am-municipality-modal-title" class="am-area-dialog m-auto max-w-lg rounded-xl border border-slate-200 p-0 backdrop:bg-slate-900/50">
        <div class="am-area-dialog-panel !p-0 bg-white text-slate-900 shadow-xl">
            <div class="shrink-0 border-b border-slate-200 px-5 py-4 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Municipality Record</p>
                <h2 id="am-municipality-modal-title" class="mt-1 text-lg font-bold text-slate-900">Add Municipality</h2>
            </div>

            <form id="am-municipality-form" class="am-area-dialog-form" method="POST" action="{{ route('areas.municipalities.store') }}">
                @csrf
                <div class="am-area-dialog-scroll !p-5 sm:!px-6">
                    @if (session('error') && ($recovery['entity'] ?? null) === 'municipality')
                        <p data-area-errors role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</p>
                    @endif
                    @if ($errors->getBag('municipality')->any())
                        <ul data-area-errors role="alert" class="mb-4 list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 p-4 pl-8 text-sm text-red-800">
                            @foreach ($errors->getBag('municipality')->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                    <input type="hidden" id="am-municipality-form-method" name="_method" value="POST">

                    <div class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="am-municipality-name" class="{{ $areaModalLabelClass }}">Municipality Name <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input id="am-municipality-name" name="name" required maxlength="255" aria-describedby="am-municipality-name-preview" class="{{ $areaModalFieldClass }}">
                            <p id="am-municipality-name-preview" data-area-value-preview="am-municipality-name" hidden class="am-area-name text-xs leading-5 text-slate-500"></p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="am-municipality-address" class="{{ $areaModalLabelClass }}">Address / Description</label>
                            <textarea id="am-municipality-address" name="address" rows="3" maxlength="500"
                                      class="{{ $areaModalFieldClass }}"></textarea>
                            @error('address', 'municipality')
                                <p data-area-errors class="mt-0.5 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="am-municipality-province" class="{{ $areaModalLabelClass }}">Province</label>
                            <input id="am-municipality-province" type="text" value="Cebu" disabled
                                   class="{{ $areaModalFieldClass }}">
                        </div>
                    </div>

                </div>
                <div class="{{ $areaModalFooterClass }}">
                    <button type="button" data-municipality-modal-close
                            class="{{ $areaModalSecondaryClass }}">
                        Cancel
                    </button>
                    <button type="submit" class="{{ $areaModalPrimaryClass }}">Save</button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- Barangay create/edit modal --}}
    <dialog id="am-barangay-modal" aria-labelledby="am-barangay-modal-title" class="am-area-dialog m-auto max-w-lg rounded-xl border border-slate-200 p-0 backdrop:bg-slate-900/50">
        <div class="am-area-dialog-panel !p-0 bg-white text-slate-900 shadow-xl">
            <div class="shrink-0 border-b border-slate-200 px-5 py-4 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Barangay Record</p>
                <h2 id="am-barangay-modal-title" class="mt-1 text-lg font-bold text-slate-900">Add Barangay</h2>
            </div>

            <form id="am-barangay-form" class="am-area-dialog-form" method="POST" action="{{ route('areas.barangays.store') }}">
                @csrf
                <div class="am-area-dialog-scroll !p-5 sm:!px-6">
                    @if (session('error') && ($recovery['entity'] ?? null) === 'barangay')
                        <p data-area-errors role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</p>
                    @endif
                    @if ($errors->getBag('barangay')->any())
                        <ul data-area-errors role="alert" class="mb-4 list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 p-4 pl-8 text-sm text-red-800">
                            @foreach ($errors->getBag('barangay')->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                    <input type="hidden" id="am-barangay-form-method" name="_method" value="POST">

                    <div class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="am-barangay-area-unit" class="{{ $areaModalLabelClass }}">
                                Municipality <span aria-hidden="true" class="ml-0.5 text-red-600">*</span>
                            </label>
                            <select id="am-barangay-area-unit" name="area_unit_id" required aria-describedby="am-barangay-parent-preview"
                                    class="{{ $areaModalFieldClass }}">
                                <option value="">Select a municipality</option>
                                @foreach ($activeMunicipalities as $muniOption)
                                    <option value="{{ $muniOption->id }}">{{ $muniOption->name }}</option>
                                @endforeach
                            </select>
                            <p id="am-barangay-parent-preview" data-area-value-preview="am-barangay-area-unit" hidden class="am-area-name text-xs leading-5 text-slate-500"></p>
                            @error('area_unit_id', 'barangay')
                                <p data-area-errors class="mt-0.5 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="am-barangay-name" class="{{ $areaModalLabelClass }}">Barangay Name <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input id="am-barangay-name" name="name" required maxlength="255" aria-describedby="am-barangay-name-preview" class="{{ $areaModalFieldClass }}">
                            <p id="am-barangay-name-preview" data-area-value-preview="am-barangay-name" hidden class="am-area-name text-xs leading-5 text-slate-500"></p>
                        </div>
                    </div>

                </div>
                <div class="{{ $areaModalFooterClass }}">
                    <button type="button" data-barangay-modal-close
                            class="{{ $areaModalSecondaryClass }}">
                        Cancel
                    </button>
                    <button type="submit" class="{{ $areaModalPrimaryClass }}">Save</button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- Read-only View Details modal --}}
    <dialog id="am-view-modal" aria-labelledby="am-view-title" class="am-area-dialog m-auto max-w-2xl rounded-xl border border-slate-200 p-0 backdrop:bg-slate-900/50">
        <div class="am-area-dialog-panel !p-0 bg-white text-slate-900 shadow-xl">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">View Area Details</p>
                    <h2 id="am-view-title" tabindex="-1" autofocus class="am-area-name mt-1 text-lg font-bold text-slate-900">Area Details</h2>
                    <p id="am-view-subtitle" class="mt-1 text-sm leading-6 text-slate-600"></p>
                </div>
                <span id="am-view-status" class="hidden"></span>
            </div>

            <div id="am-view-body" aria-live="polite" class="am-area-dialog-scroll !p-5 text-sm leading-6 text-slate-600 sm:!px-6"></div>

            <div class="{{ $areaModalFooterClass }}">
                <button type="button" data-view-modal-close
                        class="{{ $areaModalSecondaryClass }}">
                    Close
                </button>
            </div>
        </div>
    </dialog>

    {{-- Shared confirmation dialog (management-actions.js) --}}
    <dialog id="am-confirm-modal" data-area-confirm-dialog aria-labelledby="am-confirm-title" aria-describedby="am-confirm-message" class="am-area-dialog m-auto max-w-sm rounded-xl border border-slate-200 p-0 backdrop:bg-slate-900/50">
        <div class="am-area-dialog-panel !p-0 bg-white text-slate-900 shadow-xl">
            <div class="shrink-0 border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="am-confirm-title" class="text-lg font-bold text-slate-900">Are you sure?</h2>
            </div>
            <p id="am-confirm-message" class="am-area-dialog-scroll !p-5 text-sm leading-6 text-slate-600 sm:!px-6"></p>
            <div class="{{ $areaModalFooterClass }}">
                <button type="button" data-confirm-close autofocus
                        class="{{ $areaModalSecondaryClass }}">
                    Cancel
                </button>
                <button type="button" id="am-confirm-action-btn"
                        data-tone="archive"
                        class="{{ $areaModalButtonClass }} bg-red-700 text-white hover:bg-red-800 focus:ring-red-500 data-[tone=restore]:bg-emerald-700 data-[tone=restore]:hover:bg-emerald-800 data-[tone=restore]:focus:ring-emerald-500">
                    Confirm
                </button>
            </div>
        </div>
    </dialog>
