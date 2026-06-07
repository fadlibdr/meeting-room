<?php

declare(strict_types=1);

namespace App\Services\Sso;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Stage 3 F.1 — maps an Entra ID (Azure AD) identity to a local User.
 *
 * Just-in-time provisioning: an unknown email becomes a new User (when
 * auto-provision is on) with an unusable random local password — SSO is the
 * only way in for such accounts. Roles are derived from the token's AD-group
 * claim via config('sso.group_role_map'); when groups are present they are
 * authoritative (synced every login), otherwise a brand-new user falls back to
 * the configured default role and existing users keep their roles.
 */
class SsoUserProvisioner
{
    public function provision(SocialiteUser $identity): User
    {
        $email = $identity->getEmail();

        if ($email === null || $email === '') {
            throw new RuntimeException('Penyedia SSO tidak mengembalikan alamat email.');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            if (! config('sso.auto_provision')) {
                throw new RuntimeException('Akun belum terdaftar untuk SSO.');
            }

            $user = new User;
            $user->email = $email;
            $user->password = Hash::make(Str::random(48)); // unusable local password
            $user->is_active = true;
        }

        $user->name = $identity->getName() ?: ($user->name ?? $email);
        $user->email_verified_at ??= now();
        $user->save();

        $this->syncRoles($user, $identity);

        return $user;
    }

    private function syncRoles(User $user, SocialiteUser $identity): void
    {
        $map = config('sso.group_role_map', []);
        $raw = $identity->user['groups'] ?? [];
        $groups = is_array($raw) ? $raw : [];

        $codes = [];
        foreach ($groups as $groupId) {
            if (is_string($groupId) && isset($map[$groupId])) {
                $codes[] = $map[$groupId];
            }
        }

        if ($codes !== []) {
            // IdP groups are authoritative when present.
            $roleIds = Role::query()->whereIn('code', array_unique($codes))->pluck('id')->all();
            if ($roleIds !== []) {
                $user->roles()->sync($roleIds);
            }

            return;
        }

        // No group mapping: only seed a default role for a brand-new user.
        if ($user->wasRecentlyCreated && $user->roles()->count() === 0) {
            $defaultRole = Role::query()->where('code', config('sso.default_role'))->first();
            if ($defaultRole !== null) {
                $user->roles()->sync([$defaultRole->id]);
            }
        }
    }
}
