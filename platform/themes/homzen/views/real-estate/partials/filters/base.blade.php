<div @class(['wd-find-select' => in_array($style, [1, 2, 4]), 'wd-filter-select' => $style === 3, 'style-2 shadow-st' => $style === 2, 'no-left-round' => $noLeftRound ?? false]) style="min-height: 54px; height: 54px; padding: 5px 6px 5px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
    <div class="inner-group" style="flex: 1; margin: 0; padding: 0; border: none;">
        <div class="form-group-1 form-search-form form-style form-search-keyword-input w-100" data-bb-toggle="search-suggestion" style="padding: 0; margin: 0; border: none; background: transparent;">
            <div class="position-relative w-100">
                <input type="text" class="form-control" placeholder="{{ __('Search projects, condos, location...') }}" value="{{ BaseHelper::stringify(request()->query('k')) }}" name="k" style="height: 42px; border: none; background: transparent; font-size: 15px; width: 100%; box-shadow: none; outline: none; padding: 0 10px 0 0;" />
                <div data-bb-toggle="data-suggestion"></div>
            </div>
        </div>
    </div>
    <button type="submit" class="tf-btn primary" style="height: 44px; min-width: 120px; padding: 0 24px; border-radius: 8px; font-weight: 600; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
        <x-core::icon name="ti ti-search" style="font-size: 18px;" />
        {{ __('Search') }}
    </button>
</div>

<style>
    .wd-search-form, .filter-advanced { display: none !important; }
    .wd-find-select .inner-group::after, .wd-find-select .inner-group::before { display: none !important; }
    .wd-find-select .form-group-1::after { display: none !important; }
</style>
