@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

<div id="contact-form" @class(['widget-box single-property-contact', $class ?? null])>
    {{-- The "Contact Agency" block (and its leftover "Contact" heading) was
         removed deliberately. It rendered the local admin account that owns the
         row (e.g. "System Admin") with placeholder contact details — meaningless
         for a manually added property and misleading on an imported MLS listing.
         The enquiry form below is the contact path; it needs no heading of its
         own since it carries its own "Ask About this Home" title. --}}

    @if ($isProject)
        {!! apply_filters('project_right_details_info', null, $model) !!}
    @else
        {!! apply_filters('property_right_details_info', null, $model) !!}
    @endif

    @if (RealEstateHelper::isEnabledConsultForm())
        {!! apply_filters('before_consult_form', null, $model) !!}

        <div class="consult-form-header mt-3">
            <h3 class="h6 fw-bold mb-3 text-dark">{{ __('Ask About this Home') }}</h3>
        </div>

        @php
            $form = \Botble\RealEstate\Forms\Fronts\ConsultForm::create()
                ->formClass('contact-form')
                ->setFormInputWrapperClass('ip-group')
                ->modify('name', 'text', [
                    'label' => false,
                    'attr' => ['placeholder' => __('Full Name')]
                ])
                ->modify('email', 'email', [
                    'label' => false,
                    'attr' => ['placeholder' => __('Email Address')]
                ])
                ->modify('phone', 'text', [
                    'label' => false,
                    'attr' => ['placeholder' => __('Phone Number (Mobile)')]
                ])
                ->modify('content', 'textarea', [
                    'label' => false,
                    'value' => '',
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 4,
                        'placeholder' => __('I would like more information regarding the property at :name', ['name' => $model->name]),
                    ],
                ])
                ->modify('submit', 'submit', ['attr' => ['class' => 'tf-btn primary w-100 btn-send-message']])
                ->add('type', 'hidden', ['attr' => ['value' => $isProject ? 'project' : 'property']])
                ->add('data_id', 'hidden', ['attr' => ['value' => $model->getKey()]]);

            // Clean up custom fields or extra checkboxes (e.g. Schedule a Tour or Privacy Checkboxes)
            $allowedFields = ['name', 'email', 'phone', 'content', 'submit', 'type', 'data_id'];
            $fields = $form->getFields();
            foreach (array_keys($fields) as $fieldName) {
                if (!in_array($fieldName, $allowedFields)) {
                    $form->remove($fieldName);
                }
            }
        @endphp

        {!! $form->renderForm() !!}

        {!! apply_filters('after_consult_form', null, $model) !!}
    @endif
</div>

{{-- Sticky Request Info Button for Mobile View Only --}}
<div class="mobile-sticky-request-bar d-block d-lg-none">
    <a href="#contact-form" class="btn-mobile-request-info tf-btn primary w-100 d-flex align-items-center justify-content-center gap-2">
        <x-core::icon name="ti ti-mail" style="width: 20px; height: 20px;" />
        <span>{{ __('Request Info') }}</span>
    </a>
</div>

<style>
.mobile-sticky-request-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    padding: 10px 16px;
    background: #ffffff;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.12);
    border-top: 1px solid #eaedf1;
}
.mobile-sticky-request-bar .btn-mobile-request-info {
    font-weight: 700;
    font-size: 1rem;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
@media (min-width: 992px) {
    .mobile-sticky-request-bar {
        display: none !important;
    }
}
</style>
