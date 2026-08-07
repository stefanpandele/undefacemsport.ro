<?php

return [
    'meta' => [
        'title' => 'Register your organization — Unde Facem Sport',
    ],

    'form' => [
        'eyebrow' => 'New account',
        'heading' => 'Register your organization',
        'subheading' => 'It takes less than a minute. The rest of the details — locations, spaces, schedule — you fill in after approval.',

        'type' => [
            'label' => 'What kind of organization are you?',
            'hint' => 'This decides how your public page looks and what you can fill in inside your account.',
            'club' => [
                'label' => 'Sports club',
                'description' => 'You run groups, coaches and a recurring training schedule.',
            ],
            'venue' => [
                'label' => 'Sports venue',
                'description' => 'You have pitches, halls or pools — entry by ticket or rented by the hour.',
            ],
            'practice' => [
                'label' => 'Practice or clinic',
                'description' => 'You work by appointment: physiotherapy, recovery, nutrition.',
            ],
        ],

        'name' => [
            'label' => 'Organization name',
            'placeholder' => 'e.g. Club Aqua Junior',
            'club' => [
                'label' => 'Club name',
                'placeholder' => 'e.g. Club Aqua Junior',
            ],
            'venue' => [
                'label' => 'Venue name',
                'placeholder' => 'e.g. Baza Sportivă Olimpia',
            ],
            'practice' => [
                'label' => 'Practice name',
                'placeholder' => 'e.g. Cabinet Kinetic Recuperare',
            ],
        ],
        'fiscal_code' => [
            'label' => 'Fiscal code (CUI)',
            'placeholder' => 'e.g. 41234567',
        ],
        'contact_name' => [
            'label' => 'Representative name',
            'placeholder' => 'e.g. Andrei Popescu',
        ],
        'contact_phone' => [
            'label' => 'Phone',
            'placeholder' => '07xx xxx xxx',
        ],
        'contact_email' => [
            'label' => 'Email',
            'placeholder' => 'andrei@example.ro',
        ],

        'info' => 'After you submit the request, the UndeFacemSport.ro team reviews it manually within 24–48h. You will receive a confirmation email, then you can add locations, spaces and schedule.',
        'submit' => 'Send request →',
        'submitting' => 'Sending…',
    ],

    'anaf' => [
        'button' => 'Fetch from ANAF',
        'searching' => 'Searching the Trade Register…',
        'found' => 'Company found',
        'name' => 'Name',
        'address' => 'Address',
        'registration_number' => 'Trade Reg. No.',
        'vat_status' => 'VAT status',
        'vat_payer' => 'VAT payer',
        'vat_non_payer' => 'Not a VAT payer',
        'not_found' => 'We could not find any company with this fiscal code. Check it and try again.',
    ],

    'success' => [
        'heading' => 'Request sent!',
        'body' => 'Thank you! The UndeFacemSport.ro team is checking the details and will let you know as soon as your account is approved.',
        'badge' => 'PENDING APPROVAL',
        'back' => 'Back to homepage →',
    ],

    'validation' => [
        'name' => [
            'required' => 'Please enter the organization name.',
            'max' => 'The organization name must not exceed 255 characters.',
        ],
        'type' => [
            'required' => 'Please pick what kind of organization you are.',
            'invalid' => 'Please pick one of the three options.',
        ],
        'fiscal_code' => [
            'required' => 'Please enter the fiscal code (CUI).',
            'max' => 'The fiscal code must not exceed 50 characters.',
        ],
        'contact_name' => [
            'required' => "Please enter the representative's name.",
            'max' => "The representative's name must not exceed 255 characters.",
        ],
        'contact_email' => [
            'required' => 'Please enter the email address.',
            'email' => 'Please enter a valid email address.',
            'max' => 'The email address must not exceed 255 characters.',
        ],
        'contact_phone' => [
            'required' => 'Please enter the phone number.',
            'max' => 'The phone number must not exceed 50 characters.',
        ],
        'turnstile' => [
            'required' => 'Please confirm you are not a robot.',
            'failed' => 'Anti-spam verification failed. Please try again.',
            'unavailable' => 'Anti-spam verification is currently unavailable. Please try again later.',
        ],
    ],
];
