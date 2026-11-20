<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Internal;

use AltchaOrg\Altcha\Algorithm\DeriveKeyInterface;
use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Algorithm\ShaAlgorithm;
use AltchaOrg\Altcha\HmacAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;

/**
 * Maps the package enum onto the official library's algorithm objects, so no library type leaks into the public API.
 *
 * @internal
 */
final class AlgorithmMap
{
    public static function deriveKey(AltchaAlgorithm $algorithm): DeriveKeyInterface
    {
        return match ($algorithm) {
            AltchaAlgorithm::Pbkdf2Sha256 => new Pbkdf2(HmacAlgorithm::SHA256),
            AltchaAlgorithm::Pbkdf2Sha384 => new Pbkdf2(HmacAlgorithm::SHA384),
            AltchaAlgorithm::Pbkdf2Sha512 => new Pbkdf2(HmacAlgorithm::SHA512),
            AltchaAlgorithm::Sha256 => new Sha(ShaAlgorithm::SHA256),
            AltchaAlgorithm::Sha384 => new Sha(ShaAlgorithm::SHA384),
            AltchaAlgorithm::Sha512 => new Sha(ShaAlgorithm::SHA512),
        };
    }
}
