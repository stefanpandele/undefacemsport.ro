<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves Romanian company details from the public ANAF registry.
 *
 * @phpstan-type CompanyDetails array{company_name: string, address: string, registration_number: string, county: string, city: string, is_vat_payer: bool}
 */
class LookupCompanyByFiscalCode
{
    private const ENDPOINT = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

    /**
     * ANAF allows one request per second, so successful lookups are cached and
     * reused by the submit request that follows the user's manual lookup.
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * @return CompanyDetails|null Null when the code is malformed, unknown to ANAF, or the registry is unreachable.
     */
    public function handle(string $fiscalCode): ?array
    {
        $cui = $this->normalizeFiscalCode($fiscalCode);

        if ($cui === null) {
            return null;
        }

        $cached = Cache::get($this->cacheKey($cui));

        if (is_array($cached)) {
            return $cached;
        }

        $company = $this->fetch($cui);

        if ($company !== null) {
            Cache::put($this->cacheKey($cui), $company, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $company;
    }

    /**
     * Strips the optional "RO" VAT prefix and any separators ANAF does not accept.
     */
    private function normalizeFiscalCode(string $fiscalCode): ?int
    {
        $digits = preg_replace('/\D/', '', $fiscalCode) ?? '';

        if ($digits === '' || strlen($digits) > 10) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @return CompanyDetails|null
     */
    private function fetch(int $cui): ?array
    {
        try {
            $response = Http::asJson()
                ->timeout(8)
                ->post(self::ENDPOINT, [[
                    'cui' => $cui,
                    'data' => now()->toDateString(),
                ]]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        /** @var array<string, mixed>|null $company */
        $company = $response->json('found.0');

        if ($company === null) {
            return null;
        }

        return $this->toCompanyDetails($company);
    }

    /**
     * @param  array<string, mixed>  $company
     * @return CompanyDetails
     */
    private function toCompanyDetails(array $company): array
    {
        /** @var array<string, mixed> $general */
        $general = $company['date_generale'] ?? [];
        /** @var array<string, mixed> $address */
        $address = $company['adresa_sediu_social'] ?? [];
        /** @var array<string, mixed> $vat */
        $vat = $company['inregistrare_scop_Tva'] ?? [];

        return [
            'company_name' => trim((string) ($general['denumire'] ?? '')),
            'address' => trim((string) ($general['adresa'] ?? '')),
            'registration_number' => trim((string) ($general['nrRegCom'] ?? '')),
            'county' => trim((string) ($address['sdenumire_Judet'] ?? '')),
            'city' => trim((string) ($address['sdenumire_Localitate'] ?? '')),
            'is_vat_payer' => (bool) ($vat['scpTVA'] ?? false),
        ];
    }

    private function cacheKey(int $cui): string
    {
        return "anaf.company.{$cui}";
    }
}
