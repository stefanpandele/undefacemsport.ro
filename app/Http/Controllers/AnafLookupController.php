<?php

namespace App\Http\Controllers;

use App\Actions\LookupCompanyByFiscalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnafLookupController extends Controller
{
    /**
     * Resolve company details for a fiscal code so the application form can prefill them.
     */
    public function __invoke(Request $request, LookupCompanyByFiscalCode $lookupCompany): JsonResponse
    {
        $validated = $request->validate([
            'cui' => ['required', 'string', 'max:20'],
        ]);

        $company = $lookupCompany->handle($validated['cui']);

        if ($company === null) {
            return response()->json([
                'message' => __('Nu am găsit nicio firmă cu acest CUI. Verifică și încearcă din nou.'),
            ], 404);
        }

        return response()->json($company);
    }
}
