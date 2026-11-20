<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

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
}
