<div @class(['wd-find-select kash-search-bar', 'wd-filter-select' => $style === 3, 'style-2 shadow-st' => $style === 2, 'no-left-round' => $noLeftRound ?? false])>
    <div class="inner-group">
        <div class="form-group-1 form-search-form form-style form-search-keyword-input w-100" data-bb-toggle="search-suggestion">
            <div class="position-relative w-100 kash-search-input-box">
                <button type="submit" class="kash-search-inside-btn" aria-label="{{ __('Search') }}">
                    <x-core::icon name="ti ti-search" />
                </button>
                <input type="text" class="form-control kash-search-input" placeholder="{{ __('Search projects, condos, location...') }}" value="{{ BaseHelper::stringify(request()->query('k')) }}" name="k" />
                <div data-bb-toggle="data-suggestion"></div>
            </div>
        </div>
    </div>
    <button type="submit" class="tf-btn primary kash-search-desktop-btn">
        <x-core::icon name="ti ti-search" style="font-size: 18px;" />
        <span>{{ __('Search') }}</span>
    </button>
</div>

<style>
    .wd-search-form, .filter-advanced { display: none !important; }
    .wd-find-select .inner-group::after, .wd-find-select .inner-group::before { display: none !important; }
    .wd-find-select .form-group-1::after { display: none !important; }
    .flat-tab-form .form-sl { width: 100%; }
    .flat-tab .wd-find-select, .flat-tab .wd-filter-select { max-width: 100%; width: 100%; }

    .kash-search-bar {
        width: 100%;
        min-height: 64px;
        height: 64px;
        padding: 8px 8px 8px 24px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        background: #ffffff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }
    .kash-search-bar .inner-group {
        flex: 1;
        margin: 0;
        padding: 0;
        border: none;
    }
    .kash-search-bar .form-search-keyword-input {
        padding: 0;
        margin: 0;
        border: none;
        background: transparent;
    }
    .kash-search-input-box {
        display: flex;
        align-items: center;
    }
    .kash-search-inside-btn {
        display: none;
    }
    .kash-search-input {
        height: 48px;
        border: none;
        background: transparent;
        font-size: 15px;
        width: 100%;
        box-shadow: none;
        outline: none;
        padding: 0 10px 0 0;
    }
    .kash-search-desktop-btn {
        height: 48px;
        min-width: 140px;
        padding: 0 28px;
        border-radius: 10px;
        font-weight: 600;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 15px;
    }

    @media (max-width: 767.98px) {
        .kash-search-bar {
            flex-direction: row !important;
            height: 50px !important;
            min-height: 50px !important;
            padding: 4px 12px !important;
            border-radius: 12px !important;
            gap: 0 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05) !important;
            border: 1px solid #e5e7eb !important;
        }
        .kash-search-inside-btn {
            display: flex !important;
            align-items: center;
            justify-content: center;
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: transparent;
            border: none;
            color: #6b7280;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            z-index: 3;
        }
        .kash-search-input {
            height: 42px !important;
            padding-left: 34px !important;
            padding-right: 8px !important;
            font-size: 14px !important;
        }
        .kash-search-desktop-btn {
            display: none !important;
        }
    }
</style>


