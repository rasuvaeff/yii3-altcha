<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Internal;

use AltchaOrg\Altcha\Altcha;
use Rasuvaeff\Yii3Altcha\AltchaConfig;

/**
 * Builds the official library object the same way for issuing and verifying.
 *
 * Besides the signature secret it sets a key-signature secret, derived from the configured one: the issuer then
 * signs the derived key into the challenge, and the verifier checks a solution with one HMAC instead of repeating
 * the key derivation.
 *
 * @internal
 */
final class AltchaFactory
{
    private const string KEY_SIGNATURE_CONTEXT = 'rasuvaeff/yii3-altcha key signature v1';

    public static function create(AltchaConfig $config): Altcha
    {
        return new Altcha(
            hmacSignatureSecret: $config->secret,
            hmacKeySignatureSecret: hash_hmac('sha256', self::KEY_SIGNATURE_CONTEXT, $config->secret),
        );
    }
}
