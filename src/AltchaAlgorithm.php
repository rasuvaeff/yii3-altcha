<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use AltchaOrg\Altcha\Algorithm\DeriveKeyInterface;
use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Algorithm\ShaAlgorithm;
use AltchaOrg\Altcha\HmacAlgorithm;

/**
 * Key-derivation algorithms the browser widget can solve out of the box.
 * Values are the names the ALTCHA protocol puts into the challenge.
 *
 * @api
 */
enum AltchaAlgorithm: string
{
    case Pbkdf2Sha256 = 'PBKDF2/SHA-256';
    case Pbkdf2Sha384 = 'PBKDF2/SHA-384';
    case Pbkdf2Sha512 = 'PBKDF2/SHA-512';
    case Sha256 = 'SHA-256';
    case Sha384 = 'SHA-384';
    case Sha512 = 'SHA-512';

    /**
     * @internal
     */
    public function deriveKey(): DeriveKeyInterface
    {
        return match ($this) {
            self::Pbkdf2Sha256 => new Pbkdf2(HmacAlgorithm::SHA256),
            self::Pbkdf2Sha384 => new Pbkdf2(HmacAlgorithm::SHA384),
            self::Pbkdf2Sha512 => new Pbkdf2(HmacAlgorithm::SHA512),
            self::Sha256 => new Sha(ShaAlgorithm::SHA256),
            self::Sha384 => new Sha(ShaAlgorithm::SHA384),
            self::Sha512 => new Sha(ShaAlgorithm::SHA512),
        };
    }
}
