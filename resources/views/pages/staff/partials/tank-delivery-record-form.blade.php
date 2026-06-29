@php
    $record = $record ?? null;
    $delivery = $delivery ?? $record;
    $showErrors = old('_tdr_form') === $formKey;
    $isEditing = ! is_null($record);

    $fieldValue = function ($field, $default = '') use ($showErrors) {
        return $showErrors ? old($field, $default) : $default;
    };

    $dateForInput = function ($value, $fallback = '') {
        if (empty($value)) {
            return $fallback;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $fallback;
        }
    };

    $timeForInput = function ($value, $fallback = '') {
        if (empty($value)) {
            return $fallback;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $fallback;
        }
    };

    $numberForInput = function ($value) {
        return $value === null || $value === '' ? '' : number_format((float) $value, 2, '.', '');
    };

    $recordDateDefault = $isEditing
        ? $dateForInput($record->record_date, now()->format('Y-m-d'))
        : now()->format('Y-m-d');
    $recordTimeDefault = $isEditing
        ? $timeForInput($record->record_time, now()->format('H:i'))
        : now()->format('H:i');
    $plateDefault = $isEditing ? $record->plate_number : '';

    $inputBase = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
    $inputReadonly = 'h-10 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300';
    $numberInput = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-right text-theme-sm tabular-nums text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
    $computedInput = 'h-10 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-right text-theme-sm font-semibold tabular-nums text-gray-700 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300';
    $errorInput = ' border-error-300 focus:border-error-300 focus:ring-error-500/20 dark:border-error-500/50';
    $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';

    $fieldClass = fn ($field, $base = null) => ($base ?? $inputBase) . ($showErrors && $errors->has($field) ? $errorInput : '');
    $fieldError = fn ($field) => $showErrors && $errors->has($field);
    $checklistLabelForDelivery = function ($field, $label) use ($delivery) {
        $fuelType = strtolower((string) ($delivery->fuel_type ?? ''));

        if ($field === 'ethanol_blend_passed' && str_contains($fuelType, 'diesel')) {
            return 'Visual Appearance';
        }

        return $label;
    };

    $fuelKeyForDelivery = function ($fuelType) use ($fuelTypes) {
        $fuelType = strtolower(trim((string) $fuelType));

        if ($fuelType === '') {
            return null;
        }

        if (str_contains($fuelType, 'regular')) {
            return 'unleaded';
        }

        foreach ($fuelTypes as $fuel => $label) {
            $fuelLabel = strtolower((string) $label);

            if ($fuelType === $fuel || $fuelType === $fuelLabel || str_contains($fuelType, $fuel) || str_contains($fuelType, $fuelLabel)) {
                return $fuel;
            }
        }

        return null;
    };

    $deliveryFuel = $fuelKeyForDelivery($delivery->fuel_type ?? null);
    $visibleFuelTypes = $deliveryFuel && array_key_exists($deliveryFuel, $fuelTypes)
        ? [$deliveryFuel => $fuelTypes[$deliveryFuel]]
        : $fuelTypes;
@endphp

<input type="hidden" name="_tdr_form" value="{{ $formKey }}">
<input type="hidden" name="inventory_log_id" value="{{ $inventoryLogId }}">

<div class="space-y-5">
    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="{{ $labelClass }}">Tanker Delivery Record</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex max-w-full rounded-full bg-brand-50 px-3 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
                        {{ $delivery->delivery_code }}
                    </span>
                    <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ \Carbon\Carbon::parse($delivery->delivery_date)->format('M d, Y') }}
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-right sm:min-w-[240px]">
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    <p class="{{ $labelClass }}">Liters</p>
                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">
                        {{ number_format((float) ($delivery->liters_delivered ?? 0), 2) }} L
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    <p class="{{ $labelClass }}">Cost</p>
                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">
                        &#8369;{{ number_format((float) ($delivery->total_cost ?? 0), 2) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="block">
                <span class="{{ $labelClass }}">Delivery Code</span>
                <input type="text" class="mt-1.5 {{ $inputReadonly }}" value="{{ $delivery->delivery_code }}" readonly>
            </label>
            <label class="block">
                <span class="{{ $labelClass }}">Supplier</span>
                <input type="text" class="mt-1.5 {{ $inputReadonly }}" value="{{ $delivery->supplier_name }}" readonly>
            </label>
            <label class="block">
                <span class="{{ $labelClass }}">Tank / Fuel</span>
                <input type="text" class="mt-1.5 {{ $inputReadonly }}" value="{{ $delivery->tank_name }} - {{ $delivery->fuel_type }}" readonly>
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="block" data-field-shell>
                <span class="{{ $labelClass }}">Record Date</span>
                <input type="date" name="record_date" value="{{ $fieldValue('record_date', $recordDateDefault) }}" class="mt-1.5 {{ $fieldClass('record_date') }}" required>
                <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError('record_date') ? '' : 'hidden' }}">
                    @if ($showErrors)
                        @error('record_date') {{ $message }} @enderror
                    @endif
                </div>
            </label>
            <label class="block" data-field-shell>
                <span class="{{ $labelClass }}">Record Time</span>
                <input type="time" name="record_time" value="{{ $fieldValue('record_time', $recordTimeDefault) }}" class="mt-1.5 {{ $fieldClass('record_time') }}" required>
                <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError('record_time') ? '' : 'hidden' }}">
                    @if ($showErrors)
                        @error('record_time') {{ $message }} @enderror
                    @endif
                </div>
            </label>
            <label class="block" data-field-shell>
                <span class="{{ $labelClass }}">Plate Number</span>
                <input type="text" name="plate_number" value="{{ $fieldValue('plate_number', $plateDefault) }}" maxlength="60" class="mt-1.5 uppercase {{ $fieldClass('plate_number') }}" required>
                <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError('plate_number') ? '' : 'hidden' }}">
                    @if ($showErrors)
                        @error('plate_number') {{ $message }} @enderror
                    @endif
                </div>
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <div class="mb-3 flex flex-col gap-1">
            <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Inspection Checklist</h3>
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Mark every item before submitting the record.</p>
        </div>

        <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            @foreach ($checklistItems as $field => $label)
                @php
                    $displayLabel = $checklistLabelForDelivery($field, $label);
                    $checklistDefault = $isEditing ? (string) (int) $record->{$field} : '';
                    $selectedChecklistValue = (string) $fieldValue($field, $checklistDefault);
                    $hasChecklistError = $fieldError($field);
                @endphp
                <div class="grid grid-cols-1 gap-3 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,300px)] md:items-center">
                    <div class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $displayLabel }}</div>
                    <div>
                        <div
                            class="grid grid-cols-2 gap-2 rounded-lg border border-transparent p-1 {{ $hasChecklistError ? 'border-error-300 bg-error-50/70 dark:border-error-500/50 dark:bg-error-500/10' : '' }}"
                            data-checklist-group="{{ $field }}"
                            data-checklist-label="{{ $displayLabel }}">
                            <label class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                <input type="radio" name="{{ $field }}" value="1" class="size-4 accent-brand-500" required
                                    x-on:change="clearChecklistError($event.target.closest('[data-checklist-group]'))"
                                    {{ $selectedChecklistValue === '1' ? 'checked' : '' }}>
                                Passed
                            </label>
                            <label class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                                <input type="radio" name="{{ $field }}" value="0" class="size-4 accent-error-500" required
                                    x-on:change="clearChecklistError($event.target.closest('[data-checklist-group]'))"
                                    {{ $selectedChecklistValue === '0' ? 'checked' : '' }}>
                                Not Passed
                            </label>
                        </div>
                        <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-checklist-error {{ $hasChecklistError ? '' : 'hidden' }}">
                            @if ($showErrors)
                                @error($field) {{ $message }} @enderror
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <div class="mb-3 flex flex-col gap-1">
            <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Fuel Dipstick Summary</h3>
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Computed columns update while you type and are recalculated again when saved.</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="max-w-full overflow-x-auto">
                <table class="min-w-[980px] w-full table-fixed">
                    <colgroup>
                        <col class="w-[13%]">
                        <col class="w-[17%]">
                        <col class="w-[17%]">
                        <col class="w-[22%]">
                        <col class="w-[15%]">
                        <col class="w-[16%]">
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-4 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Fuel</th>
                            <th class="px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Before Unloading</th>
                            <th class="px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">After Unloading</th>
                            <th class="px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total Dispensed After Dipstick</th>
                            <th class="px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">After - Before</th>
                            <th class="px-4 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Delivery Summary</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($visibleFuelTypes as $fuel => $label)
                            @php
                                $beforeField = "before_unloading_dipstick_{$fuel}";
                                $afterField = "after_unloading_dipstick_{$fuel}";
                                $dispensedField = "total_dispensed_{$fuel}";
                                $totalField = "after_before_total_{$fuel}";
                                $summaryValue = $isEditing
                                    && $record->{$dispensedField} !== null
                                    && $record->{$totalField} !== null
                                        ? (float) $record->{$dispensedField} + (float) $record->{$totalField}
                                        : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-4 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $label }}</td>
                                <td class="px-4 py-4" data-field-shell>
                                    <input type="number" step="0.01" min="0" name="{{ $beforeField }}"
                                        class="{{ $fieldClass($beforeField, $numberInput) }} js-tdr-input"
                                        data-fuel="{{ $fuel }}"
                                        data-field-label="{{ $label }} before unloading dipstick"
                                        value="{{ $fieldValue($beforeField, $isEditing ? $numberForInput($record->{$beforeField}) : '') }}"
                                        x-on:input="calculateFuel($event.target.form, '{{ $fuel }}'); clearFieldError($event.target)"
                                        required>
                                    <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError($beforeField) ? '' : 'hidden' }}">
                                        @if ($showErrors)
                                            @error($beforeField) {{ $message }} @enderror
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4" data-field-shell>
                                    <input type="number" step="0.01" min="0" name="{{ $afterField }}"
                                        class="{{ $fieldClass($afterField, $numberInput) }} js-tdr-input"
                                        data-fuel="{{ $fuel }}"
                                        data-fuel-label="{{ $label }}"
                                        data-field-label="{{ $label }} after unloading dipstick"
                                        value="{{ $fieldValue($afterField, $isEditing ? $numberForInput($record->{$afterField}) : '') }}"
                                        x-on:input="calculateFuel($event.target.form, '{{ $fuel }}'); clearFieldError($event.target)"
                                        required>
                                    <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError($afterField) ? '' : 'hidden' }}">
                                        @if ($showErrors)
                                            @error($afterField) {{ $message }} @enderror
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4" data-field-shell>
                                    <input type="number" step="0.01" min="0" name="{{ $dispensedField }}"
                                        class="{{ $fieldClass($dispensedField, $numberInput) }} js-tdr-input"
                                        data-fuel="{{ $fuel }}"
                                        data-field-label="{{ $label }} total dispensed"
                                        value="{{ $fieldValue($dispensedField, $isEditing ? $numberForInput($record->{$dispensedField}) : '') }}"
                                        x-on:input="calculateFuel($event.target.form, '{{ $fuel }}'); clearFieldError($event.target)"
                                        required>
                                    <div class="mt-1 text-theme-xs font-medium text-error-600 dark:text-error-400 js-field-error {{ $fieldError($dispensedField) ? '' : 'hidden' }}">
                                        @if ($showErrors)
                                            @error($dispensedField) {{ $message }} @enderror
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <input type="text" class="{{ $computedInput }}" data-tdr-total="{{ $fuel }}" value="{{ $isEditing ? $numberForInput($record->{$totalField}) : '' }}" readonly>
                                </td>
                                <td class="px-4 py-4">
                                    <input type="text" class="{{ $computedInput }}" data-tdr-summary="{{ $fuel }}" value="{{ $numberForInput($summaryValue) }}" readonly>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
