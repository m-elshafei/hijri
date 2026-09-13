<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\GeoCity;
use App\Models\GeoNeighborhood;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\User;
use App\Models\ValuationRequest;
use App\Overrides\Spatie\Role;
use App\Policies\CompanyPolicy;
use App\Policies\ContractorPolicy;
use App\Policies\ContractPolicy;
use App\Policies\GeoCityPolicy;
use App\Policies\GeoNeighborhoodPolicy;
use App\Policies\OfferPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\ValuationRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        ValuationRequest::class => ValuationRequestPolicy::class,
        Partner::class => PartnerPolicy::class,
        Contractor::class => ContractorPolicy::class,
        Contract::class => ContractPolicy::class,
        Offer::class => OfferPolicy::class,
        GeoCity::class => GeoCityPolicy::class,
        GeoNeighborhood::class => GeoNeighborhoodPolicy::class,
        Company::class => CompanyPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
