<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\ValuationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    foreach ([
        'dashboard.view',
        'valuation_request.view',
        'valuation_request.create',
        'valuation_request.view_logs',
        'user.view',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('manager', 'web')->syncPermissions(Permission::all());
    app()->setLocale('ar');
});

it('renders the personal page with legacy sections', function () {
    $manager = User::factory()->create(['status' => UserStatus::Active]);
    $manager->assignRole('manager');

    ValuationRequest::query()->create([
        'number' => 'HOME-1',
        'reference' => 42,
        'state' => 'underEvaluative',
    ]);

    $html = $this->actingAs($manager)
        ->get(route('home'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('الصفحة الشخصية')
        ->toContain('بحث سريع برقم التقييم')
        ->toContain('عدد التقييمات المعتمدة')
        ->toContain('تقييم جديد')
        ->toContain('لوحة التقييمات')
        ->toContain('لوحة المستخدمين')
        ->toContain('لوحة الأحداث')
        ->toContain('أحدث التقييمات')
        ->toContain('أحدث الأحداث')
        ->toContain('اسم العميل')
        ->and($html)->not->toContain('>underEvaluative<');
});
