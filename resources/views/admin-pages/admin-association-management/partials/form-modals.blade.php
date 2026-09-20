    {{-- Create modal --}}
    <div id="create-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="create-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="create-association-title" class="text-lg font-bold text-slate-900">Add Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Create the official BFAR SAAD association master record.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.associations.store', $listState) }}" class="space-y-6 p-6">
                    @csrf
                    <div data-form-error role="alert" class="hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"></div>
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'create',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Create Association
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="edit-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="edit-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="edit-association-title" class="text-lg font-bold text-slate-900">Edit Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Update the association master information.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="{{ session('association_form.mode') === 'edit' && session('association_form.id') ? route('admin.associations.update', ['association' => session('association_form.id'), ...$listState]) : '' }}" data-edit-form class="space-y-6 p-6">
                    @csrf
                    <div data-form-error role="alert" class="hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"></div>
                    @method('PUT')
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'edit',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
