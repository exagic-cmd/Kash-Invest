@php
    $socialSharing = \Botble\Theme\Supports\ThemeSupport::getSocialSharingButtons($model->url, $model->name);
@endphp
@if($socialSharing)
    <div class="property-share-social">
        <span>{{ __('Share:') }}</span>
        <ul class="list-social d-flex align-items-center">
            @foreach($socialSharing as $social)
                <li>
                    <a title="{{ $social['name'] }}" href="{{ $social['url'] }}" class="box-icon w-40 social" target="_blank">
                        {!! $social['icon'] !!}
                    </a>
                </li>
            @endforeach
            <li>
                <button type="button"
                        class="box-icon w-40 social border-0 bg-transparent p-0 js-copy-link"
                        title="{{ __('Copy link') }}"
                        aria-label="{{ __('Copy link') }}"
                        data-copy-url="{{ $model->url }}"
                        data-copied-label="{{ __('Copied!') }}">
                    <svg class="icon-copy" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 15l6 -6"></path>
                        <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"></path>
                        <path d="M13 18l-.397 .534a5.07 5.07 0 0 1 -7.127 0a4.97 4.97 0 0 1 0 -7.071l.524 -.463"></path>
                    </svg>
                    <svg class="icon-check d-none" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12l5 5l10 -10"></path>
                    </svg>
                </button>
            </li>
        </ul>
    </div>

    <script>
        (function () {
            document.querySelectorAll('.js-copy-link').forEach(function (btn) {
                if (btn.dataset.copyBound) return;
                btn.dataset.copyBound = '1';

                btn.addEventListener('click', async function () {
                    const url = btn.getAttribute('data-copy-url');
                    const copiedLabel = btn.getAttribute('data-copied-label') || 'Copied!';
                    const originalTitle = btn.getAttribute('title');
                    const iconCopy = btn.querySelector('.icon-copy');
                    const iconCheck = btn.querySelector('.icon-check');

                    let ok = false;
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(url);
                            ok = true;
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = url;
                            ta.setAttribute('readonly', '');
                            ta.style.position = 'absolute';
                            ta.style.left = '-9999px';
                            document.body.appendChild(ta);
                            ta.select();
                            ok = document.execCommand('copy');
                            document.body.removeChild(ta);
                        }
                    } catch (e) {
                        ok = false;
                    }

                    if (ok) {
                        if (iconCopy) iconCopy.classList.add('d-none');
                        if (iconCheck) iconCheck.classList.remove('d-none');
                        btn.setAttribute('title', copiedLabel);
                        setTimeout(function () {
                            if (iconCopy) iconCopy.classList.remove('d-none');
                            if (iconCheck) iconCheck.classList.add('d-none');
                            btn.setAttribute('title', originalTitle);
                        }, 1500);
                    }
                });
            });
        })();
    </script>
@endif
