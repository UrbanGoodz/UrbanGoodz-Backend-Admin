<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A driver's licence held on file for one user in one Stranded role.
 *
 * The licence number is encrypted at rest by the cast below. Only the last
 * four digits are stored in the clear, because that is all any screen ever
 * needs to display and it keeps the plaintext out of query results, logs and
 * database exports.
 *
 * The image paths point at the PRIVATE disk. Nothing here should ever be
 * turned into a public URL -- a leaked link to a government ID cannot be
 * revoked.
 */
class UrbanGoodzStrandedVerification extends Model
{
    protected $table = 'urban_goodz_stranded_verifications';

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_SAMARITAN = 'samaritan';

    protected $fillable = [
        'user_id', 'role',
        'license_front_path', 'license_back_path', 'selfie_path',
        'license_number_encrypted', 'license_last_four', 'license_state', 'license_expires_on',
        'full_name', 'date_of_birth',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
        'phone_verified', 'background_check_passed', 'background_checked_at',
    ];

    protected $casts = [
        'license_number_encrypted' => 'encrypted',
        'license_expires_on' => 'date',
        'date_of_birth' => 'date',
        'reviewed_at' => 'datetime',
        'background_checked_at' => 'datetime',
        'phone_verified' => 'boolean',
        'background_check_passed' => 'boolean',
    ];

    /**
     * Never expose the licence number or the file paths through a JSON
     * response by accident. Anything that legitimately needs them must read
     * the attribute explicitly.
     */
    protected $hidden = [
        'license_number_encrypted',
        'license_front_path',
        'license_back_path',
        'selfie_path',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * An approved licence that has since expired is not valid identification.
     * Treating it as valid would quietly weaken the whole safety guarantee.
     */
    public function isUsable(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->license_expires_on !== null && $this->license_expires_on->isPast()) {
            return false;
        }

        return true;
    }

    public function setLicenseNumber(?string $number): void
    {
        $number = $number !== null ? trim($number) : null;

        $this->license_number_encrypted = $number;
        $this->license_last_four = $number ? substr($number, -4) : null;
    }
}
