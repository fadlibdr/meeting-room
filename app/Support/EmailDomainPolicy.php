<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SettingsService;

/**
 * Resolves the configurable email-domain restriction for user accounts.
 *
 * Both the on/off toggle and the allowed domain are runtime settings
 * (`users.email_domain_restriction`, `users.email_domain`) editable from the
 * Settings page, so an operator can relax or repoint the restriction without a
 * code change. Default: enabled, `bpjs-kesehatan.go.id` (preserves prior
 * hard-coded behaviour).
 */
final class EmailDomainPolicy
{
    public const DEFAULT_DOMAIN = 'bpjs-kesehatan.go.id';

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('users.email_domain_restriction', true);
    }

    /**
     * The allowed domain, normalised without a leading '@' (e.g. "bpjs-kesehatan.go.id").
     */
    public function domain(): string
    {
        $value = (string) $this->settings->get('users.email_domain', self::DEFAULT_DOMAIN);

        return ltrim(trim($value), '@');
    }

    /**
     * Validation rule fragment to merge into an email field, or empty when the
     * restriction is off or no domain is configured.
     *
     * @return list<string>
     */
    public function rules(): array
    {
        if (! $this->enabled() || $this->domain() === '') {
            return [];
        }

        return ['ends_with:@'.$this->domain()];
    }

    public function message(): string
    {
        return __('Email harus berdomain @:domain.', ['domain' => $this->domain()]);
    }
}
