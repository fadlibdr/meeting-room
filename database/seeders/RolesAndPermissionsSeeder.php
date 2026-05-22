<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->mapRolePermissions();
    }

    private function seedPermissions(): void
    {
        $matrix = [
            'bookings' => ['view', 'view-all', 'create', 'update', 'delete', 'submit', 'approve', 'reject', 'cancel', 'override'],
            'rooms' => ['view', 'create', 'update', 'delete', 'manage-blocks'],
            'room-facilities' => ['view', 'create', 'update', 'delete'],
            'users' => ['view', 'create', 'update', 'delete'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'permissions' => ['view'],
            'units' => ['view', 'create', 'update', 'delete'],
            'activity-logs' => ['view'],
            'app-settings' => ['view', 'update'],
            'reports' => ['view', 'export'],
        ];

        foreach ($matrix as $module => $actions) {
            foreach ($actions as $action) {
                $code = "{$module}.{$action}";
                Permission::firstOrCreate(
                    ['code' => $code],
                    [
                        'module' => $module,
                        'action' => $action,
                        'name' => ucfirst($action).' '.ucfirst(str_replace('-', ' ', $module)),
                        'description' => "Allow {$action} action on {$module}",
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            [
                'code' => 'super_admin',
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh sistem',
                'scope' => 'system',
                'is_system' => true,
            ],
            [
                'code' => 'system_admin',
                'name' => 'System Admin',
                'description' => 'Mengelola RBAC, audit, dan konfigurasi sistem',
                'scope' => 'admin',
                'is_system' => true,
            ],
            [
                'code' => 'ga_admin',
                'name' => 'GA Admin',
                'description' => 'Mengelola master ruangan dan approval queue GA',
                'scope' => 'admin',
                'is_system' => false,
            ],
            [
                'code' => 'unit_approver',
                'name' => 'Unit Approver',
                'description' => 'Menyetujui booking untuk unit sendiri',
                'scope' => 'operational',
                'is_system' => false,
            ],
            [
                'code' => 'requester',
                'name' => 'Requester',
                'description' => 'Memesan ruangan dan mengelola booking sendiri',
                'scope' => 'operational',
                'is_system' => false,
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['code' => $role['code']],
                array_merge($role, ['is_active' => true])
            );
        }
    }

    private function mapRolePermissions(): void
    {
        // Super Admin: all permissions
        $superAdmin = Role::where('code', 'super_admin')->firstOrFail();
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // System Admin: RBAC + audit + units
        $systemAdmin = Role::where('code', 'system_admin')->firstOrFail();
        $systemAdmin->permissions()->sync(
            Permission::whereIn('module', [
                'users', 'roles', 'permissions', 'units', 'activity-logs', 'app-settings',
            ])->pluck('id')
        );

        // GA Admin: rooms + facilities + can approve any booking + view-all bookings
        $gaAdmin = Role::where('code', 'ga_admin')->firstOrFail();
        $gaAdmin->permissions()->sync(
            Permission::where(function ($q) {
                $q->whereIn('module', ['rooms', 'room-facilities', 'reports'])
                    ->orWhereIn('code', [
                        'bookings.view-all',
                        'bookings.approve',
                        'bookings.reject',
                    ]);
            })->pluck('id')
        );

        // Unit Approver: view-all (filtered to unit), approve, reject + own bookings
        $unitApprover = Role::where('code', 'unit_approver')->firstOrFail();
        $unitApprover->permissions()->sync(
            Permission::whereIn('code', [
                'bookings.view',
                'bookings.view-all',
                'bookings.create',
                'bookings.update',
                'bookings.submit',
                'bookings.approve',
                'bookings.reject',
                'bookings.cancel',
                'rooms.view',
                'reports.view',
            ])->pluck('id')
        );

        // Requester: own bookings only
        $requester = Role::where('code', 'requester')->firstOrFail();
        $requester->permissions()->sync(
            Permission::whereIn('code', [
                'bookings.view',
                'bookings.create',
                'bookings.update',
                'bookings.submit',
                'bookings.cancel',
                'rooms.view',
            ])->pluck('id')
        );
    }
}
