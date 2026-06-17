@php
    $announcements = apply_filters('announcement_display_html', null);
    $canRenderAnnouncements = is_plugin_active('announcement') && $announcements && \ArchiElite\Announcement\Models\Announcement::query()->exists();
@endphp

{{-- Disabled top-header, content moved to main header --}}

