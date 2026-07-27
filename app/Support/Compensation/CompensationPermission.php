<?php

namespace App\Support\Compensation;

/**
 * Granular permissions for the Driver Pricing & Compensation surface.
 *
 * Mirrors the existing admin convention: role_id 1 is the primary administrator
 * and is authoritative; every other role is checked against the JSON `modules`
 * list on its admin_role record.
 *
 * Enforcement lives in the controller as well as the middleware, so a route that
 * is wired up without the middleware still cannot be reached without the right
 * permission.
 */
final class CompensationPermission
{
    public const VIEW_RULES = 'compensation_view_rules';
    public const CREATE_DRAFT = 'compensation_create_draft';
    public const EDIT_DRAFT = 'compensation_edit_draft';
    public const PUBLISH = 'compensation_publish';
    public const ARCHIVE = 'compensation_archive';
    public const SIMULATE = 'compensation_simulate';
    public const VIEW_CALCULATIONS = 'compensation_view_calculations';
    public const CREATE_ADJUSTMENT = 'compensation_create_adjustment';
    public const APPROVE_ADJUSTMENT = 'compensation_approve_adjustment';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [
            self::VIEW_RULES,
            self::CREATE_DRAFT,
            self::EDIT_DRAFT,
            self::PUBLISH,
            self::ARCHIVE,
            self::SIMULATE,
            self::VIEW_CALCULATIONS,
            self::CREATE_ADJUSTMENT,
            self::APPROVE_ADJUSTMENT,
        ];
    }

    /** @return array<string,string> permission => human label */
    public static function labels(): array
    {
        return [
            self::VIEW_RULES => 'View compensation rules',
            self::CREATE_DRAFT => 'Create draft rule',
            self::EDIT_DRAFT => 'Edit draft rule',
            self::PUBLISH => 'Publish rule',
            self::ARCHIVE => 'Archive rule',
            self::SIMULATE => 'Run compensation simulator',
            self::VIEW_CALCULATIONS => 'View calculation history',
            self::CREATE_ADJUSTMENT => 'Create adjustment',
            self::APPROVE_ADJUSTMENT => 'Approve adjustment',
        ];
    }

    /**
     * @param  object|null  $admin  the authenticated admin record
     */
    public static function allows(?object $admin, string $permission): bool
    {
        if ($admin === null) {
            return false;
        }

        if (!in_array($permission, self::all(), true)) {
            return false;
        }

        // Primary administrator is authoritative, matching Helpers::module_permission_check.
        if ((int) ($admin->role_id ?? 0) === 1) {
            return true;
        }

        $role = $admin->role ?? null;

        if ($role === null) {
            return false;
        }

        $modules = (array) json_decode($role->modules ?? '[]');

        return in_array($permission, $modules, true);
    }

    /**
     * Permission map for the current admin, used to hide controls the admin
     * cannot use. Hiding is cosmetic only — the controller still enforces.
     *
     * @return array<string,bool>
     */
    public static function map(?object $admin): array
    {
        $map = [];

        foreach (self::all() as $permission) {
            $map[$permission] = self::allows($admin, $permission);
        }

        return $map;
    }
}
