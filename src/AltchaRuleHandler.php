<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\ValidationContext;

/**
 * @api
 */
final readonly class AltchaRuleHandler implements RuleHandlerInterface
{
    public function __construct(
        private ?AltchaVerifier $verifier = null,
        private ?TranslatorInterface $translator = null,
        private string $translationCategory = 'yii3-altcha',
    ) {}

    #[\Override]
    public function validate(mixed $value, RuleInterface $rule, ValidationContext $context): Result
    {
        if (!$rule instanceof AltchaRule) {
            throw new UnexpectedRuleException(AltchaRule::class, $rule);
        }

        $status = \is_string($value)
            ? $this->verifier()->verify($value)
            : VerificationStatus::Malformed;

        $result = new Result();

        if ($status->isVerified()) {
            return $result;
        }

        return $result->addError(
            $this->translate($rule->getMessage()),
            [
                'property' => $context->getTranslatedProperty(),
                'status' => $status->value,
            ],
        );
    }

    private function verifier(): AltchaVerifier
    {
        return $this->verifier
            ?? AltchaRegistry::verifier()
            ?? throw new \RuntimeException('AltchaVerifier is not available. Ensure rasuvaeff/yii3-altcha bootstrap is registered.');
    }

    private function translate(string $message): string
    {
        $translator = $this->translator ?? AltchaRegistry::translator();
        if (!$translator instanceof TranslatorInterface) {
            return $message;
        }

        return $translator->translate($message, [], $this->translationCategory);
    }
}
