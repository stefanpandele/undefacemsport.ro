<?php

return [
    'meta' => [
        'title' => 'Înregistrează-ți organizația — Unde Facem Sport',
    ],

    'form' => [
        'eyebrow' => 'Cont nou',
        'heading' => 'Înregistrează-ți organizația',
        'subheading' => 'Îți ia mai puțin de un minut. Restul detaliilor — locații, spații, program — le completezi după aprobare.',

        'type' => [
            'label' => 'Ce fel de organizație ești?',
            'hint' => 'Alegerea decide cum arată pagina ta publică și ce poți completa în cont.',
            'club' => [
                'label' => 'Club sportiv',
                'description' => 'Ai grupe, antrenori și program de antrenament.',
            ],
            'venue' => [
                'label' => 'Bază sportivă',
                'description' => 'Ai terenuri, săli sau bazine — cu bilet de intrare sau închiriate cu ora.',
            ],
            'practice' => [
                'label' => 'Cabinet sau clinică',
                'description' => 'Lucrezi pe programare: kinetoterapie, recuperare, nutriție.',
            ],
        ],

        'name' => [
            'label' => 'Numele organizației',
            'placeholder' => 'ex. Club Aqua Junior',
            'club' => [
                'label' => 'Numele clubului',
                'placeholder' => 'ex. Club Aqua Junior',
            ],
            'venue' => [
                'label' => 'Numele bazei sportive',
                'placeholder' => 'ex. Baza Sportivă Olimpia',
            ],
            'practice' => [
                'label' => 'Numele cabinetului',
                'placeholder' => 'ex. Cabinet Kinetic Recuperare',
            ],
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
            'placeholder' => 'andrei@exemplu.ro',
        ],

        'info' => 'După ce trimiți cererea, echipa UndeFacemSport.ro o verifică manual în 24–48h. Vei primi un email de confirmare, apoi poți completa locațiile, spațiile și programul.',
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
        'body' => 'Mulțumim! Echipa UndeFacemSport.ro verifică datele și te anunță imediat ce contul tău e aprobat.',
        'badge' => 'ÎN AȘTEPTARE APROBARE',
        'back' => 'Înapoi la pagina principală →',
    ],

    'validation' => [
        'name' => [
            'required' => 'Introdu numele organizației.',
            'max' => 'Numele organizației nu poate depăși 255 de caractere.',
        ],
        'type' => [
            'required' => 'Alege ce fel de organizație ești.',
            'invalid' => 'Alege una dintre cele trei variante.',
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
        'turnstile' => [
            'required' => 'Confirmă că nu ești robot.',
            'failed' => 'Verificarea anti-spam a eșuat. Reîncearcă.',
            'unavailable' => 'Verificarea anti-spam nu este disponibilă momentan. Reîncearcă mai târziu.',
        ],
    ],
];
