<?php

declare(strict_types=1);

use App\Enums\Commercial\OfferEstatePaymentStatus;
use App\Enums\Commercial\OfferEstateStatus;
use App\Enums\Commercial\PartyContactOwnerType;
use App\Enums\Commercial\PartyContactStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Contractor;
use App\Models\GeoCity;
use App\Models\GeoNeighborhood;
use App\Models\Offer;
use App\Models\OfferEstate;
use App\Models\Partner;
use App\Models\PartyContact;
use App\Models\Property;
use App\Models\User;
use App\Models\ValuationRequest;
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
        'valuation_request.send',
        'valuation_request.cancel',
        'valuation_request.view_deleted',
        'valuation_request.change_coordinator',
        'valuation_request.change_property_type',
        'valuation_request.manage_fee_shares',
        'valuation_request.qima_upload',
        'partner.view',
        'partner.create',
        'partner.edit',
        'partner.delete',
        'partner.activate',
        'contractor.view',
        'contractor.create',
        'contractor.edit',
        'contractor.delete',
        'contractor.activate',
        'offer.view',
        'offer.create',
        'offer.edit',
        'offer.activate',
        'offer.deactivate',
        'geo_city.view',
        'geo_city.create',
        'geo_city.delete',
        'geo_neighborhood.view',
        'geo_neighborhood.create',
        'geo_neighborhood.delete',
        'company.view',
        'company.edit',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('manager', 'web')->syncPermissions($permissions);
    app()->setLocale('ar');
});

function mutationsManager(): User
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('manager');

    return $user;
}

it('creates updates activates and deactivates partners contractors and offers', function () {
    $manager = mutationsManager();

    $this->actingAs($manager)
        ->get(route('dashboard.partners.create'))
        ->assertOk();

    $this->actingAs($manager)
        ->post(route('dashboard.partners.store'), [
            'name' => 'شريك CRUD',
            'email' => 'partner-crud@example.com',
            'phone_number' => '0501111111',
        ])
        ->assertRedirect();

    $partner = Partner::query()->where('email', 'partner-crud@example.com')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('dashboard.partners.edit', $partner))
        ->assertOk();

    $this->actingAs($manager)
        ->put(route('dashboard.partners.update', $partner), [
            'name' => 'شريك محدّث',
            'email' => 'partner-crud@example.com',
            'phone_number' => '0502222222',
        ])
        ->assertRedirect();

    expect($partner->fresh()->name)->toBe('شريك محدّث')
        ->and($partner->fresh()->phone_number)->toBe('0502222222');

    $this->actingAs($manager)
        ->post(route('dashboard.partners.activate', $partner))
        ->assertRedirect();
    expect($partner->fresh()->isActive())->toBeTrue();

    $contact = PartyContact::query()->create([
        'legacy_id' => 770001,
        'partner_id' => $partner->id,
        'owner_type' => PartyContactOwnerType::Partner,
        'name' => 'جهة',
        'email' => 'pc-crud@example.com',
        'status' => PartyContactStatus::Active,
    ]);

    $this->actingAs($manager)
        ->post(route('dashboard.partners.contacts.deactivate', [$partner, $contact]))
        ->assertRedirect();
    expect($contact->fresh()->status)->toBe(PartyContactStatus::Inactive);

    $this->actingAs($manager)
        ->post(route('dashboard.contractors.store'), [
            'name' => 'مقاول CRUD',
            'email' => 'contractor-crud@example.com',
            'phone_number' => '0503333333',
        ])
        ->assertRedirect();

    $contractor = Contractor::query()->where('email', 'contractor-crud@example.com')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('dashboard.contractors.edit', $contractor))
        ->assertOk();

    $this->actingAs($manager)
        ->put(route('dashboard.contractors.update', $contractor), [
            'name' => 'مقاول محدّث',
            'email' => 'contractor-crud@example.com',
            'fees' => 25,
        ])
        ->assertRedirect();

    expect($contractor->fresh()->name)->toBe('مقاول محدّث')
        ->and((int) $contractor->fresh()->fees)->toBe(25);

    $this->actingAs($manager)
        ->post(route('dashboard.contractors.activate', $contractor))
        ->assertRedirect();
    expect($contractor->fresh()->isActive())->toBeTrue();

    $this->actingAs($manager)
        ->delete(route('dashboard.contractors.destroy', $contractor))
        ->assertRedirect();
    expect($contractor->fresh()->state)->toBe(Contractor::STATE_INACTIVE);

    $this->actingAs($manager)
        ->get(route('dashboard.offers.create'))
        ->assertOk();

    $this->actingAs($manager)
        ->post(route('dashboard.offers.store'), [
            'number' => 'OFF-CRUD-1',
            'partner_id' => $partner->id,
            'partner_name' => $partner->fresh()->name,
            'city' => 'جدة',
            'offered_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $offer = Offer::query()->where('number', 'OFF-CRUD-1')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('dashboard.offers.edit', $offer))
        ->assertOk();

    $this->actingAs($manager)
        ->put(route('dashboard.offers.update', $offer), [
            'number' => 'OFF-CRUD-1',
            'partner_id' => $partner->id,
            'partner_name' => 'شريك العرض',
            'city' => 'الدمام',
            'offered_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    expect($offer->fresh()->city)->toBe('الدمام')
        ->and($offer->fresh()->partner_name)->toBe('شريك العرض');

    $this->actingAs($manager)
        ->post(route('dashboard.offers.activate', $offer))
        ->assertRedirect();
    expect($offer->fresh()->isAccepted())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.offers.deactivate', $offer))
        ->assertRedirect();
    expect($offer->fresh()->state)->toBe(Offer::STATE_REJECTED);

    $estate = OfferEstate::query()->create([
        'legacy_id' => 770010,
        'offer_id' => $offer->id,
        'estate_type' => 'فيلا',
        'fees' => 900,
        'area' => 300,
        'payment_status' => OfferEstatePaymentStatus::Unpaid,
        'status' => OfferEstateStatus::Draft,
    ]);

    $this->actingAs($manager)
        ->post(route('dashboard.offers.estates.activate', [$offer, $estate]))
        ->assertRedirect();
    expect($estate->fresh()->status)->toBe(OfferEstateStatus::Active);

    $this->actingAs($manager)
        ->post(route('dashboard.offers.estates.deactivate', [$offer, $estate]))
        ->assertRedirect();
    expect($estate->fresh()->status)->toBe(OfferEstateStatus::Inactive);

    $this->actingAs($manager)
        ->delete(route('dashboard.partners.destroy', $partner))
        ->assertRedirect();
    expect(Partner::withTrashed()->find($partner->id)?->trashed())->toBeTrue()
        ->and(Partner::withTrashed()->find($partner->id)?->state)->toBe(Partner::STATE_INACTIVE);

    $partnerB = Partner::query()->create([
        'name' => 'شريك إيقاف',
        'email' => 'partner-off@example.com',
        'state' => Partner::STATE_ACTIVE,
    ]);
    $this->actingAs($manager)
        ->post(route('dashboard.partners.deactivate', $partnerB))
        ->assertRedirect();
    expect(Partner::withTrashed()->find($partnerB->id)?->trashed())->toBeTrue()
        ->and(Partner::withTrashed()->find($partnerB->id)?->state)->toBe(Partner::STATE_INACTIVE);
});

it('creates and deletes geo cities and neighborhoods', function () {
    $manager = mutationsManager();

    $this->actingAs($manager)
        ->get(route('dashboard.geo-cities.create'))
        ->assertOk();

    $this->actingAs($manager)
        ->post(route('dashboard.geo-cities.store'), [
            'name_ar' => 'مدينة اختبار',
            'name_en' => 'Test City',
        ])
        ->assertRedirect();

    $city = GeoCity::query()->where('name_ar', 'مدينة اختبار')->firstOrFail();

    $this->actingAs($manager)
        ->post(route('dashboard.geo-neighborhoods.store'), [
            'city_id' => $city->id,
            'name_ar' => 'حي اختبار',
            'name_en' => 'Test Neighborhood',
        ])
        ->assertRedirect();

    $neighborhood = GeoNeighborhood::query()->where('name_ar', 'حي اختبار')->firstOrFail();
    expect($neighborhood->city_id)->toBe($city->id);

    $this->actingAs($manager)
        ->delete(route('dashboard.geo-neighborhoods.destroy', $neighborhood))
        ->assertRedirect();
    expect(GeoNeighborhood::query()->find($neighborhood->id))->toBeNull();

    $this->actingAs($manager)
        ->delete(route('dashboard.geo-cities.destroy', $city))
        ->assertRedirect();
    expect(GeoCity::query()->find($city->id))->toBeNull();
});

it('updates company profile and shares', function () {
    $manager = mutationsManager();
    $company = Company::query()->create([
        'name' => 'شركة أصل',
        'name_en' => 'Original Co',
        'default_coordinator_share' => 10,
        'default_evaluator_share' => 20,
        'default_manager_share' => 70,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard.companies.edit', $company))
        ->assertOk();

    $this->actingAs($manager)
        ->put(route('dashboard.companies.profile', $company), [
            'name' => 'شركة محدّثة',
            'name_en' => 'Updated Co',
            'phone_number' => '0111111111',
            'address' => 'الرياض',
        ])
        ->assertRedirect();

    expect($company->fresh()->name)->toBe('شركة محدّثة')
        ->and($company->fresh()->phone_number)->toBe('0111111111');

    $this->actingAs($manager)
        ->put(route('dashboard.companies.shares', $company), [
            'default_coordinator_share' => 15,
            'default_evaluator_share' => 25,
            'default_manager_share' => 60,
        ])
        ->assertRedirect();

    $fresh = $company->fresh();
    expect((int) $fresh->default_coordinator_share)->toBe(15)
        ->and((int) $fresh->default_evaluator_share)->toBe(25)
        ->and((int) $fresh->default_manager_share)->toBe(60);
});

it('edits valuation fields toggles qima updates fees cancels and restores', function () {
    $manager = mutationsManager();
    $coordinator = User::factory()->create(['status' => UserStatus::Active]);
    $coordinator->assignRole(Role::findOrCreate('coordinator', 'web'));

    $request = ValuationRequest::query()->create([
        'number' => 'R-MUT-1',
        'reference' => 88001,
        'state' => 'waiting',
        'approve' => 0,
        'coordinator_user_id' => $manager->id,
        'evaluator_user_id' => $manager->id,
    ]);
    Property::query()->create([
        'valuation_request_id' => $request->id,
        'customer_name' => 'عميل أصلي',
        'property_kind' => 'سكني',
        'property_type' => 'شقة',
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard.valuation-requests.edit', $request))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('dashboard.valuation-requests.edit-info', $request))
        ->assertOk();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.under-evaluation', $request))
        ->assertRedirect();
    expect($request->fresh()->state)->toBe('underEvaluative');

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.change-coordinator', $request), [
            'coordinator_user_id' => $coordinator->id,
        ])
        ->assertRedirect();
    expect($request->fresh()->coordinator_user_id)->toBe($coordinator->id);

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.change-property-type', $request), [
            'property_kind' => 'تجاري',
            'property_type' => 'محل',
        ])
        ->assertRedirect();
    expect($request->fresh()->property?->property_kind)->toBe('تجاري')
        ->and($request->fresh()->property?->property_type)->toBe('محل');

    $this->actingAs($manager)
        ->put(route('dashboard.valuation-requests.fee-shares', $request), [
            'coordinator_share' => 20,
            'evaluator_share' => 30,
            'manager_share' => 50,
        ])
        ->assertRedirect();

    expect($request->fresh()->feeShares()->exists())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.qima-toggle', $request), [
            'uploaded_on_qima' => true,
        ])
        ->assertRedirect();
    expect($request->fresh()->uploaded_on_qima)->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.qima-toggle', $request), [
            'uploaded_on_qima' => false,
        ])
        ->assertRedirect();
    expect($request->fresh()->uploaded_on_qima)->toBeFalse();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.cancel', $request))
        ->assertRedirect();
    expect(ValuationRequest::withTrashed()->find($request->id)?->trashed())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.restore', $request->id))
        ->assertRedirect();
    expect(ValuationRequest::query()->find($request->id))->not->toBeNull();
});
