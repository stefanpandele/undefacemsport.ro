<?php

return [
    'meta' => [
        'title' => 'Register your club — Unde Facem Sport',
    ],

    'form' => [
        'eyebrow' => 'New club account',
        'heading' => 'Register your club',
        'subheading' => 'It takes less than a minute. The rest of the details — locations, sports, schedule — you fill in after approval.',

        'club_name' => [
            'label' => 'Club name',
            'placeholder' => 'e.g. Club Aqua Junior',
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
            'placeholder' => 'andrei@clubultau.ro',
        ],

        'info' => 'After you submit the request, the UndeFacemSport.ro team reviews it manually within 24–48h. You will receive a confirmation email, then you can add locations, sports and schedule.',
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
        'body' => 'Thank you! The UndeFacemSport.ro team is checking the details and will let you know as soon as your club is approved.',
        'badge' => 'PENDING APPROVAL',
        'back' => 'Back to homepage →',
    ],

    'validation' => [
        'club_name' => [
            'required' => 'Please enter the club name.',
            'max' => 'The club name must not exceed 255 characters.',
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
    ],
];
