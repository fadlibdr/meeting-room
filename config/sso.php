<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Single Sign-On (Stage 3 F.1 — Microsoft Entra ID / Azure AD, OIDC)
    |--------------------------------------------------------------------------
    |
    | SSO is OFF by default; the email/password login stays the only path until
    | the Entra app registration credentials (AZURE_*) are set and SSO_ENABLED
    | is turned on. The redirect/callback routes 404 while disabled.
    */

    'enabled' => (bool) env('SSO_ENABLED', false),

    // Just-in-time provision a User on first SSO login. When false, only
    // pre-existing users (matched by email) may sign in via SSO.
    'auto_provision' => (bool) env('SSO_AUTO_PROVISION', true),

    // Role assigned to a freshly provisioned user when no AD group maps to a role.
    'default_role' => env('SSO_DEFAULT_ROLE', 'requester'),

    /*
    | Map Entra ID group object-ids -> application role codes. When the id_token
    | carries a `groups` claim, the matching roles are treated as authoritative
    | (synced on every login). Leave a var unset to skip that mapping.
    */
    'group_role_map' => array_filter([
        (string) env('SSO_GROUP_SUPER_ADMIN') => 'super_admin',
        (string) env('SSO_GROUP_SYSTEM_ADMIN') => 'system_admin',
        (string) env('SSO_GROUP_GA_ADMIN') => 'ga_admin',
        (string) env('SSO_GROUP_UNIT_APPROVER') => 'unit_approver',
        (string) env('SSO_GROUP_FRONT_OFFICE') => 'front_office',
        (string) env('SSO_GROUP_REQUESTER') => 'requester',
    ], fn (string $key): bool => $key !== '', ARRAY_FILTER_USE_KEY),

];
