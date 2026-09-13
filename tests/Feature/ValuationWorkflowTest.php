<?php

declare(strict_types=1);

use App\Actions\Valuation\ApproveValuationRequestAction;
use App\Actions\Valuation\CreateValuationRequestAction;
use App\Actions\Valuation\GetApprovedValuationsCountAction;
use App\Actions\Valuation\InvalidateValuationDashboardStatsCacheAction;
use App\Actions\Valuation\MarkEvaluatedAction;
use App\Actions\Valuation\SendValuationRequestAction;
use App\Actions\Valuation\UnapproveValuationRequestAction;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    foreach ([
        'valuation_request.view',
        'valuation_request.create',
        'valuation_request.edit',
        'valuation_request.approve_final',
        'valuation_request.unapprove',
        'valuation_request.mark_evaluated',
        'valuation_request.send',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('manager', 'web')->syncPermissions([
        'valuation_request.view',
        'valuation_request.create',
        'valuation_request.edit',
        'valuation_request.approve_final',
        'valuation_request.unapprove',
        'valuation_request.mark_evaluated',
        'valuation_request.send',
    ]);
});

it('creates sends evaluates and approves a valuation with cache invalidation', function () {
    $manager = User::factory()->create(['status' => UserStatus::Active]);
    $manager->assignRole('manager');

    $evaluator = User::factory()->create(['status' => UserStatus::Active]);

    $created = app(CreateValuationRequestAction::class)->execute($manager, [
        'customer_name' => 'Test Customer',
        'evaluator_user_id' => $evaluator->id,
        'property_kind' => 'سكني',
        'property_type' => 'فيلا',
    ]);

    expect($created->state)->toBe('waiting')
        ->and($created->reference)->toBe((int) $created->id + 10000)
        ->and($created->property)->not->toBeNull();

    app(SendValuationRequestAction::class)->execute($manager, $created);
    expect($created->fresh()->state)->toBe('underEvaluative');

    app(MarkEvaluatedAction::class)->execute($manager, $created->fresh());
    expect($created->fresh()->state)->toBe('تم التقييم');

    Cache::put('valuation.dashboard.approved_count', 999, 300);
    app(ApproveValuationRequestAction::class)->execute($manager, $created->fresh());

    expect($created->fresh()->isFinallyApproved())->toBeTrue()
        ->and(Cache::get('valuation.dashboard.approved_count'))->toBeNull();

    $stats = app(GetApprovedValuationsCountAction::class)->execute();
    expect($stats['approved_count'])->toBeGreaterThanOrEqual(1);

    app(UnapproveValuationRequestAction::class)->execute($manager, $created->fresh());
    expect($created->fresh()->isFinallyApproved())->toBeFalse();
});

it('invalidates dashboard cache keys', function () {
    foreach (InvalidateValuationDashboardStatsCacheAction::CACHE_KEYS as $key) {
        Cache::put($key, 'x', 300);
    }

    app(InvalidateValuationDashboardStatsCacheAction::class)->execute();

    foreach (InvalidateValuationDashboardStatsCacheAction::CACHE_KEYS as $key) {
        expect(Cache::has($key))->toBeFalse();
    }
});
