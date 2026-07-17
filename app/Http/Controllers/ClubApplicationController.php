<?php

namespace App\Http\Controllers;

use App\Actions\LookupCompanyByFiscalCode;
use App\Http\Requests\StoreClubApplicationRequest;
use App\Models\ClubApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClubApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/ClubApplication/Create');
    }

    public function store(StoreClubApplicationRequest $request, LookupCompanyByFiscalCode $lookupCompany): RedirectResponse
    {
        $company = $lookupCompany->handle($request->validated('fiscal_code'));

        $application = new ClubApplication($request->validated());

        if ($company !== null) {
            $application->company_name = $company['company_name'];
            $application->address = $company['address'];
            $application->is_vat_payer = $company['is_vat_payer'];
            $application->county = $company['county'];
            $application->city = $company['city'];
        }

        $application->save();

        return to_route('club-application.create');
    }
}
