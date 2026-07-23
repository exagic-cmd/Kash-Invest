{{--
    Lead capture. Used twice (hero + bottom), so it takes a $formId to keep
    label/input ids unique.

    This POSTs to the real, existing consult endpoint rather than a stub — the
    Consult model (re_consults) and `public.send.consult` already exist, so no
    backend was built for this task. The controller reads `type` + `data_id` to
    attach the enquiry to the project.

    NOTE: `postSendConsult` aborts 404 unless the consult form is enabled in
    Real Estate settings, and appends captcha rules when the captcha plugin is
    active. Both are existing app-level settings, not something this template
    should override.
--}}
@php
    $formId = $formId ?? 'lead';
    $action = \Illuminate\Support\Facades\Route::has('public.send.consult')
        ? route('public.send.consult')
        : '#'; // TODO: placeholder if the consult route is ever removed.
@endphp

@if (session('success_msg'))
    <div class="kl-alert" role="status">{{ session('success_msg') }}</div>
@endif

<form class="kl-form" method="POST" action="{{ $action }}" id="{{ $formId }}">
    @csrf
    <input type="hidden" name="type" value="project">
    <input type="hidden" name="data_id" value="{{ $landing['id'] }}">

    <div class="kl-form__row">
        <div class="kl-field">
            <label for="{{ $formId }}-name">Name</label>
            <input type="text" id="{{ $formId }}-name" name="name" required
                   autocomplete="name" value="{{ old('name') }}">
        </div>
        <div class="kl-field">
            <label for="{{ $formId }}-phone">Phone</label>
            <input type="tel" id="{{ $formId }}-phone" name="phone"
                   autocomplete="tel" value="{{ old('phone') }}">
        </div>
    </div>

    <div class="kl-field">
        <label for="{{ $formId }}-email">Email</label>
        <input type="email" id="{{ $formId }}-email" name="email"
               autocomplete="email" value="{{ old('email') }}">
    </div>

    <div class="kl-field">
        <label for="{{ $formId }}-content">Message</label>
        <textarea id="{{ $formId }}-content" name="content" required
                  placeholder="Which suite types are you interested in?">{{ old('content') }}</textarea>
    </div>

    @if (is_plugin_active('captcha'))
        {!! \Botble\Captcha\Facades\Captcha::display() !!}
    @endif

    @error('name') <div class="kl-form__note" style="color:var(--kl-accent)">{{ $message }}</div> @enderror
    @error('content') <div class="kl-form__note" style="color:var(--kl-accent)">{{ $message }}</div> @enderror

    <button type="submit" class="kl-btn kl-btn--block">{{ $landing['cta']['primary'] }}</button>

    <p class="kl-form__note">
        By registering you consent to be contacted about {{ $landing['name'] }}. No obligation, and you can opt out at any time.
    </p>
</form>
