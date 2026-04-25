<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = $this->loadRoles();
        $units = $this->loadUnits();

        $superAdmin = $this->createUser([
            'employee_no' => 'EMP000001',
            'name' => 'Super Admin',
            'email' => 'superadmin@bpjs-kesehatan.go.id',
            'job_title' => 'Super Administrator',
            'unit_id' => null,
            'is_active' => true,
        ], $roles['super_admin']);

        $systemAdmin = $this->createUser([
            'employee_no' => 'EMP000002',
            'name' => 'System Admin',
            'email' => 'sysadmin@bpjs-kesehatan.go.id',
            'job_title' => 'System Administrator',
            'unit_id' => null,
            'is_active' => true,
        ], $roles['system_admin']);

        $gaAdmin = $this->createUser([
            'employee_no' => 'EMP000003',
            'name' => 'Budi Pratama',
            'email' => 'ga.admin@bpjs-kesehatan.go.id',
            'job_title' => 'Kepala Biro Umum',
            'unit_id' => $units['BIRO-UMUM']->id,
            'is_active' => true,
        ], $roles['ga_admin']);

        // Unit approvers — one per direktorat
        $approverSdm = $this->createUser([
            'employee_no' => 'EMP000010',
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@bpjs-kesehatan.go.id',
            'job_title' => 'Direktur SDM dan Umum',
            'unit_id' => $units['DIR-SDM-UMUM']->id,
            'is_active' => true,
        ], $roles['unit_approver']);

        $approverIt = $this->createUser([
            'employee_no' => 'EMP000011',
            'name' => 'Siti Rahma',
            'email' => 'siti.rahma@bpjs-kesehatan.go.id',
            'job_title' => 'Direktur Teknologi Informasi',
            'unit_id' => $units['DIR-IT']->id,
            'is_active' => true,
        ], $roles['unit_approver']);

        $approverKepesertaan = $this->createUser([
            'employee_no' => 'EMP000012',
            'name' => 'Ahmad Hidayat',
            'email' => 'ahmad.hidayat@bpjs-kesehatan.go.id',
            'job_title' => 'Direktur Kepesertaan',
            'unit_id' => $units['DIR-KEPESERTAAN']->id,
            'is_active' => true,
        ], $roles['unit_approver']);

        // Requesters with approver_user_id pointing at the right unit approver
        $this->createUser([
            'employee_no' => 'EMP000020',
            'name' => 'Dewi Lestari',
            'email' => 'dewi.lestari@bpjs-kesehatan.go.id',
            'job_title' => 'Staff Biro Umum',
            'unit_id' => $units['BIRO-UMUM']->id,
            'approver_user_id' => $approverSdm->id,
            'is_active' => true,
        ], $roles['requester']);

        $this->createUser([
            'employee_no' => 'EMP000021',
            'name' => 'Eko Prasetyo',
            'email' => 'eko.prasetyo@bpjs-kesehatan.go.id',
            'job_title' => 'Software Engineer',
            'unit_id' => $units['BIRO-PENGEMBANGAN-IT']->id,
            'approver_user_id' => $approverIt->id,
            'is_active' => true,
        ], $roles['requester']);

        $this->createUser([
            'employee_no' => 'EMP000022',
            'name' => 'Rina Wulandari',
            'email' => 'rina.wulandari@bpjs-kesehatan.go.id',
            'job_title' => 'Analis Kepesertaan',
            'unit_id' => $units['DIR-KEPESERTAAN']->id,
            'approver_user_id' => $approverKepesertaan->id,
            'is_active' => true,
        ], $roles['requester']);

        // Inactive user — for testing EnsureUserIsActive middleware in Sprint 1
        $this->createUser([
            'employee_no' => 'EMP000023',
            'name' => 'Hari Nugroho',
            'email' => 'hari.nugroho@bpjs-kesehatan.go.id',
            'job_title' => 'Mantan Staff',
            'unit_id' => $units['BIRO-UMUM']->id,
            'approver_user_id' => $approverSdm->id,
            'is_active' => false,
        ], $roles['requester']);
    }

    /**
     * @return array<string, Role>
     */
    private function loadRoles(): array
    {
        return Role::all()->keyBy('code')->all();
    }

    /**
     * @return array<string, Unit>
     */
    private function loadUnits(): array
    {
        return Unit::all()->keyBy('code')->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUser(array $data, Role $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            array_merge($data, [
                'password' => Hash::make('password'),
                'timezone' => 'Asia/Jakarta',
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
            ])
        );

        // Attach role if not already attached
        if (! $user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role->id, [
                'is_primary' => true,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }
}
