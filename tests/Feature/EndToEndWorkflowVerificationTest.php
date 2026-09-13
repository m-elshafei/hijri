<?php

declare(strict_types=1);

use App\Enums\Commercial\OfferEstatePaymentStatus;
use App\Enums\Commercial\OfferEstateStatus;
use App\Enums\Commercial\PartyContactOwnerType;
use App\Enums\Commercial\PartyContactStatus;
use App\Enums\UserStatus;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\Offer;
use App\Models\OfferEstate;
use App\Models\Partner;
use App\Models\PartyContact;
use App\Models\PropertyTotal;
use App\Models\User;
use App\Models\ValuationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        'valuation_request.qima_upload',
        'valuation_request.qima_lock',
        'partner.view',
        'partner.create',
        'partner.edit',
        'contractor.view',
        'contractor.create',
        'contractor.edit',
        'contract.view',
        'contract.mark_paid',
        'offer.view',
        'offer.create',
        'offer.edit',
        'offer.activate',
        'financial.view',
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
        'partner.create',
        'partner.edit',
        'contractor.view',
        'contractor.create',
        'contractor.edit',
        'contract.view',
        'offer.view',
        'offer.create',
        'offer.edit',
        'offer.activate',
    ]);
    Role::findOrCreate('evaluator', 'web')->syncPermissions([
        'dashboard.view',
        'valuation_request.view',
        'valuation_request.edit',
        'valuation_request.mark_evaluated',
        'valuation_request.export_pdf',
    ]);

    config([
        'report_pdf.enabled' => true,
        'report_pdf.queue' => false,
        'activitylog.enabled' => true,
    ]);

    app()->setLocale('ar');
});

function e2eUser(string $role): User
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole($role);

    return $user;
}

it('renders translated valuation state labels not raw enums', function () {
    $manager = e2eUser('manager');

    ValuationRequest::query()->create([
        'legacy_id' => 950001,
        'reference' => 950001,
        'number' => 'R-950001',
        'state' => 'underEvaluative',
        'approve' => 0,
    ]);

    $html = $this->actingAs($manager)
        ->get(route('dashboard.valuation-requests.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('تحت التقييم')
        ->and($html)->not->toContain('>underEvaluative<');
});

it('runs the full valuation lifecycle with persistence and policy blocks', function () {
    $manager = e2eUser('manager');
    $coordinator = e2eUser('coordinator');
    $evaluator = e2eUser('evaluator');

    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.store'), [
            'customer_name' => 'عميل اختبار',
            'owner_name' => 'مالك اختبار',
            'property_kind' => 'سكني',
            'property_type' => 'شقة',
            'coordinator_user_id' => $coordinator->id,
            'evaluator_user_id' => $evaluator->id,
        ])
        ->assertRedirect();

    $request = ValuationRequest::query()->latest('id')->firstOrFail();
    expect($request->state)->toBe('waiting');

    $this->actingAs($coordinator)
        ->put(route('dashboard.valuation-requests.update', $request), [
            'customer_name' => 'عميل محدث',
            'owner_name' => 'مالك محدث',
            'property_kind' => 'سكني',
            'property_type' => 'شقة',
            'deposit_number' => 'DEP-1',
        ])
        ->assertRedirect();

    expect($request->fresh()->property?->customer_name)->toBe('عميل محدث');

    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.change-evaluator', $request), [
            'evaluator_user_id' => $evaluator->id,
        ])
        ->assertRedirect();

    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.send', $request))
        ->assertRedirect();

    expect($request->fresh()->state)->toBe('underEvaluative');

    $this->actingAs($evaluator)
        ->put(route('dashboard.valuation-requests.update-info', $request), [
            'owner_name' => 'مالك تقييم',
            'instrument_no' => 'INST-9',
        ])
        ->assertRedirect();

    PropertyTotal::query()->updateOrCreate(
        ['property_id' => $request->fresh()->property->id],
        [
            'legacy_id' => 950010,
            'total_amount' => 500000,
            'total_amount_manual' => null,
        ]
    );

    $this->actingAs($evaluator)
        ->post(route('dashboard.valuation-requests.mark-evaluated', $request))
        ->assertRedirect();

    expect($request->fresh()->state)->toBe('تم التقييم');

    $this->actingAs($coordinator)
        ->post(route('dashboard.valuation-requests.approve', $request))
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.approve', $request))
        ->assertRedirect();

    expect($request->fresh()->isFinallyApproved())->toBeTrue();

    foreach (['enforcement', 'full', 'full-draft'] as $variant) {
        $payload = $this->actingAs($manager)
            ->getJson(route('dashboard.valuation-requests.exports.queue', [$request, $variant]))
            ->assertOk()
            ->json();

        $this->actingAs($manager)
            ->get(route('dashboard.valuation-requests.exports.download', [
                'valuationRequest' => $request,
                'variant' => $variant,
                'key' => $payload['key'],
            ]))
            ->assertOk();
    }

    Storage::fake('local');
    $file = UploadedFile::fake()->create('qima.pdf', 100, 'application/pdf');

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.official-report', $request), [
            'official_report' => $file,
        ])
        ->assertRedirect();

    expect($request->fresh()->isQimaLocked())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.valuation-requests.unapprove', $request))
        ->assertRedirect();

    $unlocked = $request->fresh();
    expect($unlocked->isFinallyApproved())->toBeFalse()
        ->and($unlocked->isQimaLocked())->toBeFalse();
});

it('runs commercial create/pay flows with persistence', function () {
    $manager = e2eUser('manager');

    $this->actingAs($manager)
        ->post(route('dashboard.partners.store'), [
            'name' => 'شريك اختبار',
            'email' => 'partner-e2e@example.com',
            'phone_number' => '0500000001',
        ])
        ->assertRedirect();

    $partner = Partner::query()->where('email', 'partner-e2e@example.com')->firstOrFail();

    PartyContact::query()->create([
        'legacy_id' => 960001,
        'partner_id' => $partner->id,
        'owner_type' => PartyContactOwnerType::Partner,
        'name' => 'جهة شريك',
        'email' => 'pc@example.com',
        'status' => PartyContactStatus::Active,
    ]);

    $this->actingAs($manager)
        ->post(route('dashboard.offers.store'), [
            'number' => 'OFF-E2E-1',
            'partner_id' => $partner->id,
            'partner_name' => $partner->name,
            'city' => 'الرياض',
            'offered_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $offer = Offer::query()->where('number', 'OFF-E2E-1')->firstOrFail();

    $estate = OfferEstate::query()->create([
        'legacy_id' => 960001,
        'offer_id' => $offer->id,
        'estate_type' => 'أرض',
        'fees' => 1500,
        'area' => 400,
        'payment_status' => OfferEstatePaymentStatus::Unpaid,
        'status' => OfferEstateStatus::Active,
    ]);

    $this->actingAs($manager)
        ->post(route('dashboard.offers.estates.mark-paid', [$offer, $estate]))
        ->assertRedirect();

    expect($estate->fresh()->isPaid())->toBeTrue();

    $this->actingAs($manager)
        ->post(route('dashboard.contractors.store'), [
            'name' => 'مقاول اختبار',
            'email' => 'contractor-e2e@example.com',
            'phone_number' => '0500000002',
        ])
        ->assertRedirect();

    $contractor = Contractor::query()->where('email', 'contractor-e2e@example.com')->firstOrFail();

    PartyContact::query()->create([
        'legacy_id' => 960002,
        'contractor_id' => $contractor->id,
        'owner_type' => PartyContactOwnerType::Contractor,
        'name' => 'جهة مقاول',
        'email' => 'cc@example.com',
        'status' => PartyContactStatus::Active,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard.partners.show', $partner))
        ->assertOk()
        ->assertSee('pc@example.com')
        ->assertSee(__('Draft'));

    $this->actingAs($manager)
        ->get(route('dashboard.offers.show', $offer))
        ->assertOk()
        ->assertSee('أرض')
        ->assertSee(__('Paid'));

    $this->actingAs($manager)
        ->get(route('dashboard.contractors.show', $contractor))
        ->assertOk()
        ->assertSee('cc@example.com');

    $valuation = ValuationRequest::query()->create([
        'legacy_id' => 960099,
        'number' => 'R-CONTRACT',
        'state' => 'approve',
        'approve' => 1,
    ]);

    $contract = Contract::query()->create([
        'legacy_id' => 960099,
        'contractor_id' => $contractor->id,
        'valuation_request_id' => $valuation->id,
        'state' => Contract::STATE_UNPAID,
    ]);

    $this->actingAs($manager)
        ->post(route('dashboard.contracts.mark-paid', $contract))
        ->assertRedirect();

    expect($contract->fresh()->isPaid())->toBeTrue();
});
