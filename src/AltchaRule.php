<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Attribute;
use Closure;
use Yiisoft\Validator\Rule\Trait\SkipOnEmptyTrait;
use Yiisoft\Validator\Rule\Trait\SkipOnErrorTrait;
use Yiisoft\Validator\Rule\Trait\WhenTrait;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\SkipOnEmptyInterface;
use Yiisoft\Validator\SkipOnErrorInterface;
use Yiisoft\Validator\WhenInterface;

/**
 * One rule per property: the first check spends the challenge, a second one would see it as replayed.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class AltchaRule implements RuleInterface, SkipOnEmptyInterface, SkipOnErrorInterface, WhenInterface
{
    use SkipOnEmptyTrait;
    use SkipOnErrorTrait;
    use WhenTrait;

    public function __construct(
        private readonly string $message = 'The CAPTCHA verification failed.',
        bool|callable|null $skipOnEmpty = null,
        private readonly bool $skipOnError = false,
        private readonly ?Closure $when = null,
    ) {
        /** @var bool|callable(mixed, bool):bool|null $skipOnEmpty */
        $this->skipOnEmpty = $skipOnEmpty;
    }

    #[\Override]
    public function getHandler(): string
    {
        return AltchaRuleHandler::class;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
