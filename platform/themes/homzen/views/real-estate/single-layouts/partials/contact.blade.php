@php
    $model = $model ?? $property ?? null;
    $isProject = $model instanceof \Botble\RealEstate\Models\Project;
@endphp

<div id="contact-form" @class(['widget-box single-property-contact', $class ?? null])>
    @if (! RealEstateHelper::hideAgentInfoInPropertyDetailPage() && ($account = $model->author) && $account->exists)
        <div class="h7 title fw-7">{{ __('Contact Agency') }}</div>

        <div class="box-avatar">
            <div class="avatar avt-100 round">
                <a href="{{ $account->url }}" class="d-block">
                    {{ RvMedia::image($account->avatar?->url ?: $account->avatar_url, $account->name) }}
                </a>
            </div>
            <div class="info line-clamp-1">
                <div @class(['text-1 name', 'fw-7' => theme_option('real_estate_bold_agent_name', 'no') === 'yes'])>
                    <a href="{{ $account->url }}">{{ $account->name }} {!! $account->badge !!}</a>
                </div>
                @if ($account->phone && ! setting('real_estate_hide_agency_phone', false) && ! $account->hide_phone)
                    <a href="tel:{{ $account->phone }}" class="info-item">{{ $account->phone }}</a>
                @elseif($hotline = theme_option('hotline'))
                    <a href="tel:{{ $hotline }}" class="info-item">{{ $hotline }}</a>
                @endif
                @if ($account->email && ! setting('real_estate_hide_agency_email', false) && ! $account->hide_email)
                    <a href="mailto:{{ $account->email }}" class="info-item">{{ $account->email }}</a>
                @endif
            </div>
        </div>
    @else
        <div class="h7 title fw-7">{{ __('Contact') }}</div>
    @endif

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
                    'value' => __('I would like more information regarding the property at :name', ['name' => $model->name]),
                    'attr' => ['class' => 'form-control', 'rows' => 4]
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

