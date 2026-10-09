            <form data-officer-review data-member-form
                  method="POST"
                  action="{{ route('membership.applications.review', $application) }}"
                  class="space-y-5 rounded-xl border bg-white p-6">
                @csrf
                @method('PATCH')

                <h2 class="text-lg font-bold">Field Officer review</h2>

                <p class="text-sm leading-6 text-slate-600">
                    Approval creates an official member. Rejection retains the
                    application and its reason. A recorded decision cannot be changed here.
                </p>

                <label class="block">
                    <span class="block font-semibold">Decision *</span>
                    <select name="decision" required class="am-user-control mt-2">
                        <option value="">Select decision</option>
                        <option value="Approved" @selected(old('decision') === 'Approved')>
                            Approve
                        </option>
                        <option value="Rejected" @selected(old('decision') === 'Rejected')>
                            Reject
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="block font-semibold">
                        Rejection reason — required when rejecting
                    </span>
                    <textarea name="rejection_reason"
                              maxlength="2000"
                              rows="3"
                              class="am-user-control mt-2">{{ is_string(old('rejection_reason')) ? old('rejection_reason') : '' }}</textarea>
                </label>

                <label class="flex items-start gap-3 text-sm leading-6">
                    <input class="mt-1"
                           type="checkbox"
                           required
                           name="decision_confirmed"
                           value="1">
                    I have checked this application and understand that the
                    recorded decision cannot be changed here.
                </label>

                <button class="am-user-button am-user-button-primary">
                    Confirm and record decision
                </button>
            </form>

