<div class="preload preload-container">
    @if ($icon = theme_option('preloader_icon'))
        <div class="preloader-icon-wrapper">
            {!! RvMedia::image($icon, __('Preloader icon'), attributes: ['class' => 'preloader-icon'], lazy: false) !!}
        </div>
    @else
        <div class="simple-spinner"></div>
    @endif
</div>
