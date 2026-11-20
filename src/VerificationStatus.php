<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

/**
 * @api
 */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case Expired = 'expired';
    case InvalidSignature = 'invalid_signature';
    case InvalidSolution = 'invalid_solution';
    case Replayed = 'replayed';
    case Malformed = 'malformed';

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
