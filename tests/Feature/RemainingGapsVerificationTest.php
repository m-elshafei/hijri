<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Property;
use App\Models\PropertyPicture;
use App\Models\User;
use App\Models\ValuationRequest;
use App\Overrides\Spatie\Role as AppRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    foreach ([
        'dashboard.view',
        'user.view',
        'user.create',
        'user.edit',
        'user.delete',
        'role.view',
        'role.create',
        'role.edit',
        'role.delete',
        'valuation_request.view',
        'valuation_request.edit',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('super-admin', 'web')->syncPermissions(Permission::all());
    Role::findOrCreate('manager', 'web')->syncPermissions(Permission::all());
    app()->setLocale('ar');
});

function remainingAdmin(): User
{
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('OldPass123'),
    ]);
    $user->assignRole('super-admin');

    return $user;
}

it('updates and deletes users and changes password', function () {
    $admin = remainingAdmin();
    $targetRole = Role::findOrCreate('manager', 'web');

    $target = User::factory()->create([
        'status' => UserStatus::Active,
        'name' => 'قبل التعديل',
        'username' => 'before_edit',
        'email' => 'before@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.users.create'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.users.edit', $target))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('dashboard.users.update', $target), [
            'name' => 'بعد التعديل',
            'username' => 'after_edit',
            'email' => 'after@example.com',
            'roles' => [$targetRole->id],
        ])
        ->assertRedirect(route('dashboard.users.index'));

    $fresh = $target->fresh();
    expect($fresh->name)->toBe('بعد التعديل')
        ->and($fresh->username)->toBe('after_edit')
        ->and($fresh->email)->toBe('after@example.com')
        ->and($fresh->hasRole('manager'))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard.users.change-password'))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('dashboard.users.update-password'), [
            'old_password' => 'OldPass123',
            'new_password' => 'NewPass456',
            'new_password_confirmation' => 'NewPass456',
        ])
        ->assertRedirect();

    expect(Hash::check('NewPass456', $admin->fresh()->password))->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('dashboard.users.destroy', $target))
        ->assertRedirect(route('dashboard.users.index'));

    expect(User::withTrashed()->find($target->id)?->trashed())->toBeTrue();
});

it('creates updates and deletes roles and loads permissions view', function () {
    $admin = remainingAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard.roles.create'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('dashboard.roles.store'), [
            'name' => 'custom-role',
            'ar_name' => 'دور مخصص',
            'guard_name' => 'web',
        ])
        ->assertRedirect(route('dashboard.roles.index'));

    $role = AppRole::query()->where('name', 'custom-role')->firstOrFail();
    expect($role->ar_name)->toBe('دور مخصص');

    $this->actingAs($admin)
        ->get(route('dashboard.roles.edit', $role))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.roles.permissions', $role))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('dashboard.roles.update', $role), [
            'name' => 'custom-role',
            'ar_name' => 'دور محدّث',
            'guard_name' => 'web',
        ])
        ->assertRedirect(route('dashboard.roles.index'));

    expect($role->fresh()->ar_name)->toBe('دور محدّث');

    $this->actingAs($admin)
        ->delete(route('dashboard.roles.destroy', $role))
        ->assertRedirect(route('dashboard.roles.index'));

    expect(AppRole::query()->find($role->id))->toBeNull();
});

it('deletes property pictures and downloads attachments zip', function () {
    $admin = remainingAdmin();

    $base = sys_get_temp_dir().'/muqayem_pic_'.uniqid('', true);
    $requestDir = $base.'/990001';
    mkdir($requestDir, 0777, true);
    $absolute = $requestDir.'/facade.jpg';
    file_put_contents($absolute, 'fake-image-bytes');

    config(['legacy_import.picture_search_paths' => [$base]]);

    $request = ValuationRequest::query()->create([
        'legacy_id' => 990001,
        'number' => 'R-PIC-1',
        'reference' => 990001,
        'state' => 'underEvaluative',
        'approve' => 0,
    ]);
    $property = Property::query()->create([
        'legacy_id' => 990001,
        'valuation_request_id' => $request->id,
        'customer_name' => 'عميل صور',
    ]);

    $keep = PropertyPicture::query()->create([
        'legacy_id' => 990011,
        'property_id' => $property->id,
        'filename' => 'facade.jpg',
        'description' => 'واجهة',
        'file_exists' => true,
        'relative_path' => '990001/facade.jpg',
        'sort_order' => 1,
    ]);

    $drop = PropertyPicture::query()->create([
        'legacy_id' => 990012,
        'property_id' => $property->id,
        'filename' => 'gone.jpg',
        'description' => 'للحذف',
        'file_exists' => false,
        'relative_path' => null,
        'sort_order' => 2,
    ]);

    $this->actingAs($admin)
        ->delete(route('dashboard.valuation-requests.pictures.destroy', [$request, $drop]))
        ->assertRedirect();

    expect(PropertyPicture::query()->find($drop->id))->toBeNull()
        ->and(PropertyPicture::query()->find($keep->id))->not->toBeNull();

    $response = $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.attachments-zip', $request));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('zip');

    $fileResponse = $this->actingAs($admin)
        ->get(route('dashboard.property-pictures.file', $keep));
    expect($fileResponse->getStatusCode())->toBe(200);

    @unlink($absolute);
    @rmdir($requestDir);
    @rmdir($base);
});

it('opens advanced search deleted qima-pending and barcode screens', function () {
    Permission::findOrCreate('valuation_request.advanced_search', 'web');
    Permission::findOrCreate('valuation_request.view_deleted', 'web');
    Permission::findOrCreate('valuation_request.qima_upload', 'web');
    Role::findByName('super-admin', 'web')->givePermissionTo([
        'valuation_request.advanced_search',
        'valuation_request.view_deleted',
        'valuation_request.qima_upload',
    ]);

    $admin = remainingAdmin();

    $request = ValuationRequest::query()->create([
        'number' => 'R-SCR-1',
        'reference' => 991001,
        'state' => 'waiting',
        'approve' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.advanced-search'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.advanced-search.results', ['q' => 'R-SCR']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.deleted'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.qima-pending'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.barcode', $request))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard.valuation-requests.quick-search', ['q' => (string) $request->reference]))
        ->assertRedirect();
});
