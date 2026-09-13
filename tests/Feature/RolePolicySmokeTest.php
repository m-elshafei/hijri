<?php

declare(strict_types=1);

use App\Enums\Commercial\OfferEstatePaymentStatus;
use App\Enums\Commercial\OfferEstateStatus;
use App\Enums\Commercial\PartyContactOwnerType;
use App\Enums\Commercial\PartyContactStatus;
use App\Enums\UserStatus;
use App\Models\Contractor;
use App\Models\Offer;
use App\Models\OfferEstate;
use App\Models\Partner;
use App\Models\PartyContact;
use App\Models\Property;
use App\Models\PropertyTotal;
use App\Models\User;
use App\Models\ValuationRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $permissions = [
        'dashboard.view',
        'valuation_request.view',
        'valuation_request.create',
        'valuation_request.edit',
        'valuation_request.approve_final',
        'valuation_request.unapprove',
        'valuation_request.mark_evaluated',
        'valuation_request.send',
        'valuation_request.override_amount',
        'valuation_request.change_evaluator',
        'valuation_request.change_coordinator',
        'valuation_request.export_pdf',
        'financial.view',
        'partner.view',
        'contractor.view',
        'offer.view',
        'offer.edit',
        'geo_city.view',
        'company.edit',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('manager', 'web')->syncPermissions($permissions);

    Role::findOrCreate('coordinator', 'web')->syncPermissions([
        'dashboard.view',
        'valuation_request.view',
        'valuation_request.create',
        'valuation_request.edit',
        'valuation_request.send',
        'valuation_request.change_evaluator',
        'valuation_request.export_pdf',
        'partner.view',
        'contractor.view',
        'offer.view',
        'offer.edit',
        'geo_city.view',
    ]);

    Role::findOrCreate('evaluator', 'web')->syncPermissions([
        'dashboard.view',
        'valuation_request.view',
        'valuation_request.edit',
        'valuation_request.mark_evaluated',
        'valuation_request.export_pdf',
    ]);
});

function makeRoleUser(string $role): User
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole($role);

    return $user;
}

/**
 * @return array{manager:User,coordinator:User,evaluator:User,otherEvaluator:User,request:ValuationRequest}
 */
function makeValuationForRoles(): array
{
    $manager = makeRoleUser('manager');
    $coordinator = makeRoleUser('coordinator');
    $evaluator = makeRoleUser('evaluator');
    $otherEvaluator = makeRoleUser('evaluator');

    $request = ValuationRequest::query()->create([
        'legacy_id' => 930001,
        'reference' => 930001,
        'number' => 'R-930001',
        'state' => 'تم التقييم',
        'approve' => 0,
        'coordinator_user_id' => $coordinator->id,
        'evaluator_user_id' => $evaluator->id,
    ]);

    $property = Property::query()->create([
        'legacy_id' => 930001,
        'valuation_request_id' => $request->id,
        'property_kind' => 'سكني',
        'property_type' => 'شقة',
    ]);

    PropertyTotal::query()->create([
        'legacy_id' => 930001,
        'property_id' => $property->id,
        'total_amount' => 1000,
        'total_amount_manual' => null,
    ]);

    return compact('manager', 'coordinator', 'evaluator', 'otherEvaluator', 'request');
}

it('allows manager sensitive routes and blocks coordinator via policy', function () {
    ['manager' => $manager, 'coordinator' => $coordinator, 'request' => $request] = makeValuationForRoles();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.approve', $request))
        ->assertRedirect();
    expect($request->fresh()->isFinallyApproved())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.unapprove', $request))
        ->assertRedirect();

    $request->forceFill(['state' => 'تم التقييم', 'approve' => 0, 'ended_at' => null])->save();
    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.approve', $request))
        ->assertForbidden();

    $request->forceFill(['state' => 'approve', 'approve' => 1])->save();
    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.unapprove', $request))
        ->assertForbidden();

    $request->forceFill(['state' => 'waiting', 'approve' => 0])->save();
    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.override-amount', $request), [
            'total_amount_manual' => 9999,
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.override-amount', $request), [
            'total_amount_manual' => 9999,
        ])
        ->assertRedirect();

    $this->actingAs($manager)->get(route('dashboard.financial.index'))->assertOk();
    $this->actingAs($coordinator)->get(route('dashboard.financial.index'))->assertForbidden();
});

it('allows only assigned evaluator to mark evaluated via route', function () {
    ['evaluator' => $evaluator, 'otherEvaluator' => $otherEvaluator, 'request' => $request] = makeValuationForRoles();

    $request->forceFill(['state' => 'underEvaluative', 'approve' => 0])->save();

    $this->actingAs($otherEvaluator)
        ->post(route('dashboard.valuation-requests.mark-evaluated', $request))
        ->assertForbidden();

    $this->actingAs($evaluator)
        ->post(route('dashboard.valuation-requests.mark-evaluated', $request))
        ->assertRedirect();

    expect($request->fresh()->state)->toBe('تم التقييم');
});

it('loads commercial list screens for manager without error', function () {
    $manager = makeRoleUser('manager');

    $this->actingAs($manager)->get(route('dashboard.partners.index'))->assertOk();
    $this->actingAs($manager)->get(route('dashboard.contractors.index'))->assertOk();
    $this->actingAs($manager)->get(route('dashboard.offers.index'))->assertOk();
    $this->actingAs($manager)->get(route('dashboard.geo-cities.index'))->assertOk();
    $this->actingAs($manager)->get(route('dashboard.valuation-requests.index'))->assertOk();
});

it('shows offer estates and party contacts on detail screens', function () {
    $manager = makeRoleUser('manager');

    $partner = Partner::query()->create([
        'legacy_id' => 940001,
        'name' => 'Partner Smoke',
        'state' => 1,
    ]);

    $contractor = Contractor::query()->create([
        'legacy_id' => 940001,
        'name' => 'Contractor Smoke',
        'state' => 1,
    ]);

    $offer = Offer::query()->create([
        'legacy_id' => 940001,
        'number' => 'O-940001',
        'partner_id' => $partner->id,
        'partner_name' => $partner->name,
        'state' => 1,
    ]);

    OfferEstate::query()->create([
        'legacy_id' => 940001,
        'offer_id' => $offer->id,
        'estate_type' => 'Villa Smoke',
        'payment_status' => OfferEstatePaymentStatus::Unpaid,
        'status' => OfferEstateStatus::Active,
    ]);

    PartyContact::query()->create([
        'legacy_id' => 940001,
        'owner_type' => PartyContactOwnerType::Partner,
        'partner_id' => $partner->id,
        'name' => 'Partner Contact',
        'email' => 'partner@example.com',
        'status' => PartyContactStatus::Active,
    ]);

    PartyContact::query()->create([
        'legacy_id' => 940002,
        'owner_type' => PartyContactOwnerType::Contractor,
        'contractor_id' => $contractor->id,
        'name' => 'Contractor Contact',
        'email' => 'contractor@example.com',
        'status' => PartyContactStatus::Active,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard.offers.show', $offer))
        ->assertOk()
        ->assertSee('Villa Smoke');
    $this->actingAs($manager)
        ->get(route('dashboard.partners.show', $partner))
        ->assertOk()
        ->assertSee('partner@example.com');

    $this->actingAs($manager)
        ->get(route('dashboard.contractors.show', $contractor))
        ->assertOk()
        ->assertSee('contractor@example.com');
});

it('does not lazy-load on the main valuation list', function () {
    ['manager' => $manager, 'request' => $request] = makeValuationForRoles();

    Model::preventLazyLoading();

    try {
        $this->actingAs($manager)
            ->get(route('dashboard.valuation-requests.index'))
            ->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($request->id)->toBeGreaterThan(0);
});

it('gates menu-facing permissions correctly per role', function () {
    $manager = makeRoleUser('manager');
    $coordinator = makeRoleUser('coordinator');
    $evaluator = makeRoleUser('evaluator');

    expect($manager->can('valuation_request.approve_final'))->toBeTrue()
        ->and($manager->can('financial.view'))->toBeTrue()
        ->and($manager->can('valuation_request.override_amount'))->toBeTrue()
        ->and($manager->can('valuation_request.change_evaluator'))->toBeTrue()
        ->and($manager->can('valuation_request.change_coordinator'))->toBeTrue();

    expect($coordinator->can('valuation_request.approve_final'))->toBeFalse()
        ->and($coordinator->can('valuation_request.unapprove'))->toBeFalse()
        ->and($coordinator->can('valuation_request.override_amount'))->toBeFalse()
        ->and($coordinator->can('financial.view'))->toBeFalse()
        ->and($coordinator->can('offer.view'))->toBeTrue();

    expect($evaluator->can('valuation_request.mark_evaluated'))->toBeTrue()
        ->and($evaluator->can('valuation_request.approve_final'))->toBeFalse()
        ->and($evaluator->can('financial.view'))->toBeFalse()
        ->and($evaluator->can('offer.view'))->toBeFalse();
});
