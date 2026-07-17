<?php

return [
    'meta' => [
        'title' => 'Înregistrează-ți clubul — Unde Facem Sport',
    ],

    'form' => [
        'eyebrow' => 'Cont nou de club',
        'heading' => 'Înregistrează-ți clubul',
        'subheading' => 'Îți ia mai puțin de un minut. Restul detaliilor — locații, sporturi, program — le completezi după aprobare.',

        'club_name' => [
            'label' => 'Numele clubului',
            'placeholder' => 'ex. Club Aqua Junior',
        ],
        'fiscal_code' => [
            'label' => 'CUI',
            'placeholder' => 'ex. 41234567',
        ],
        'contact_name' => [
            'label' => 'Nume reprezentant',
            'placeholder' => 'ex. Andrei Popescu',
        ],
        'contact_phone' => [
            'label' => 'Telefon',
            'placeholder' => '07xx xxx xxx',
        ],
        'contact_email' => [
            'label' => 'Email',
            'placeholder' => 'andrei@clubultau.ro',
        ],

        'info' => 'După ce trimiți cererea, echipa UndeFacemSport.ro o verifică manual în 24–48h. Vei primi un email de confirmare, apoi poți completa locațiile, sporturile și programul.',
        'submit' => 'Trimite cererea →',
        'submitting' => 'Se trimite…',
    ],

    'anaf' => [
        'button' => 'Preia de la ANAF',
        'searching' => 'Se caută în Registrul Comerțului…',
        'found' => 'Firmă găsită',
        'name' => 'Denumire',
        'address' => 'Adresă',
        'registration_number' => 'Nr. Reg. Com.',
        'vat_status' => 'Status TVA',
        'vat_payer' => 'Plătitor de TVA',
        'vat_non_payer' => 'Neplătitor de TVA',
        'not_found' => 'Nu am găsit nicio firmă cu acest CUI. Verifică și încearcă din nou.',
    ],

    'success' => [
        'heading' => 'Cerere trimisă!',
        'body' => 'Mulțumim! Echipa UndeFacemSport.ro verifică datele și te anunță imediat ce clubul tău e aprobat.',
        'badge' => 'ÎN AȘTEPTARE APROBARE',
        'back' => 'Înapoi la pagina principală →',
    ],

    'validation' => [
        'club_name' => [
            'required' => 'Introdu numele clubului.',
            'max' => 'Numele clubului nu poate depăși 255 de caractere.',
        ],
        'fiscal_code' => [
            'required' => 'Introdu codul fiscal (CUI).',
            'max' => 'Codul fiscal nu poate depăși 50 de caractere.',
        ],
        'contact_name' => [
            'required' => 'Introdu numele reprezentantului.',
            'max' => 'Numele reprezentantului nu poate depăși 255 de caractere.',
        ],
        'contact_email' => [
            'required' => 'Introdu adresa de email.',
            'email' => 'Introdu o adresă de email validă.',
            'max' => 'Adresa de email nu poate depăși 255 de caractere.',
        ],
        'contact_phone' => [
            'required' => 'Introdu numărul de telefon.',
            'max' => 'Numărul de telefon nu poate depăși 50 de caractere.',
        ],
    ],
];
