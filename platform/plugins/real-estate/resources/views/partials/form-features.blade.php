<div class="features-select-wrapper">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('custom-features-styles')) return;
            var style = document.createElement('style');
            style.id = 'custom-features-styles';
            style.innerHTML = `
                /* Extremely high specificity selectors to override any global/theme select2 styling */
                .features-select-wrapper .select2-container {
                    width: 100% !important;
                }
                
                /* The select box container */
                body .features-select-wrapper .select2-container .select2-selection--multiple {
                    min-height: 48px !important;
                    padding: 6px 12px !important;
                    border-radius: 8px !important;
                    border: 1px solid var(--tblr-border-color, rgba(255, 255, 255, 0.15)) !important;
                    background-color: var(--tblr-bg-surface, rgba(30, 41, 59, 0.2)) !important;
                    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease !important;
                    display: flex !important;
                    align-items: center !important;
                    flex-wrap: wrap !important;
                    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08) !important;
                }
                
                /* Focus and Open States */
                body .features-select-wrapper .select2-container--focus .select2-selection--multiple,
                body .features-select-wrapper .select2-container--open .select2-selection--multiple {
                    border-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.6) !important;
                    box-shadow: 0 0 0 .2rem rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.18), inset 0 1px 2px rgba(0, 0, 0, 0.08) !important;
                    background-color: var(--tblr-bg-surface, rgba(30, 41, 59, 0.35)) !important;
                }
                
                /* The tag selection rendering list */
                body .features-select-wrapper .select2-selection__rendered {
                    display: flex !important;
                    flex-wrap: wrap !important;
                    align-items: center !important;
                    gap: 6px !important;
                    width: 100% !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    list-style: none !important;
                }

                /* Individual Tag Item (Premium Pill style) */
                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 6px !important;
                    background-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.11) !important;
                    border: 1px solid rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.3) !important;
                    border-radius: 999px !important;
                    color: var(--tblr-primary, #206bc4) !important;
                    padding: 4px 12px !important;
                    margin: 2px 0 !important;
                    font-size: 13px !important;
                    font-weight: 500 !important;
                    line-height: 1.4 !important;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
                }

                /* Hover states on tags */
                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice:hover {
                    background-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.18) !important;
                    border-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.5) !important;
                    transform: translateY(-1px) !important;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
                }

                /* Re-order elements: display text on left (order 1), close button on right (order 2) */
                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice__display {
                    order: 1 !important;
                    padding: 0 !important;
                }

                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice__remove {
                    order: 2 !important;
                    position: static !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    width: 14px !important;
                    height: 14px !important;
                    margin: 0 0 0 2px !important;
                    padding: 0 !important;
                    border: none !important;
                    border-radius: 50% !important;
                    /* Overwrite default background url in select2-bootstrap-5 theme */
                    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23206bc4' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M4 12L12 4M4 4l8 8'/%3e%3c/svg%3e") center/0.55rem auto no-repeat !important;
                    text-indent: -9999px !important;
                    overflow: hidden !important;
                    opacity: 0.65 !important;
                    cursor: pointer !important;
                    transition: all 0.15s ease-in-out !important;
                }

                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice__remove:hover {
                    opacity: 1 !important;
                    background-color: rgba(229, 72, 77, 0.15) !important;
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23e5484d' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M4 12L12 4M4 4l8 8'/%3e%3c/svg%3e") !important;
                    transform: scale(1.15) !important;
                }

                /* Inline search text field */
                body .features-select-wrapper .select2-container .select2-search--inline {
                    margin: 0 !important;
                    display: inline-flex !important;
                    flex-grow: 1 !important;
                }

                body .features-select-wrapper .select2-container .select2-search--inline .select2-search__field {
                    margin: 0 !important;
                    height: 28px !important;
                    font-size: 13.5px !important;
                    color: var(--tblr-body-color, #212529) !important;
                    background: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                    padding: 0 4px !important;
                }

                /* Placeholder text */
                body .features-select-wrapper .select2-container .select2-selection__placeholder {
                    color: var(--tblr-secondary, #9aa4b2) !important;
                    font-size: 13.5px !important;
                }

                /* Standalone Clear Selection button (when allowClear is true) */
                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__clear {
                    position: absolute !important;
                    top: 50% !important;
                    transform: translateY(-50%) !important;
                    right: 12px !important;
                    margin: 0 !important;
                    width: 18px !important;
                    height: 18px !important;
                    border-radius: 50% !important;
                    background: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.08) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%239aa4b2' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M4 12L12 4M4 4l8 8'/%3e%3c/svg%3e") center/0.6rem auto no-repeat !important;
                    text-indent: -9999px !important;
                    overflow: hidden !important;
                    opacity: 0.7 !important;
                    transition: all 0.15s ease-in-out !important;
                }

                body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__clear:hover {
                    opacity: 1 !important;
                    background-color: rgba(var(--tblr-primary-rgb, 32, 107, 196), 0.15) !important;
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23e5484d' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M4 12L12 4M4 4l8 8'/%3e%3c/svg%3e") !important;
                }

                /* Hint and description */
                .features-select-wrapper .features-select-hint {
                    font-size: 12px !important;
                    color: var(--tblr-secondary, #9aa4b2) !important;
                    margin: 10px 4px 0 !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 6px !important;
                    opacity: 0.85 !important;
                }

                .features-select-wrapper .features-select-hint::before {
                    content: "💡";
                    font-size: 14px;
                }

                /* Dark mode enhancements */
                html[data-bs-theme="dark"] body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice,
                [data-bs-theme="dark"] body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice {
                    background-color: rgba(56, 139, 253, 0.15) !important;
                    border-color: rgba(56, 139, 253, 0.35) !important;
                    color: #58a6ff !important;
                }
                html[data-bs-theme="dark"] body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice__remove,
                [data-bs-theme="dark"] body .features-select-wrapper .select2-container .select2-selection--multiple .select2-selection__choice__remove {
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%2358a6ff' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M4 12L12 4M4 4l8 8'/%3e%3c/svg%3e") !important;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
    {{-- Searchable multi-select instead of an endless checkbox wall. Botble's
         core.js auto-initialises select2 on `.select-search-full`, so you can
         type to find a feature. Submits `features[]` exactly like before. --}}
    <select
        name="features[]"
        class="form-control select-search-full"
        multiple
        data-placeholder="{{ __('Type to search and select features…') }}"
        data-allow-clear="true"
    >
        @foreach ($features->sortBy('name') as $feature)
            <option value="{{ $feature->id }}" @selected(in_array($feature->id, $selectedFeatures))>{{ $feature->name }}</option>
        @endforeach
    </select>
    <p class="features-select-hint">
        {{ __('Start typing to find a feature. Selected features show as tags — click × to remove one.') }}
    </p>
</div>
