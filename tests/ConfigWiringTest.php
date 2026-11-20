<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3Altcha\AltchaAlgorithm;
use Rasuvaeff\Yii3Altcha\AltchaConfig;
use Rasuvaeff\Yii3Altcha\AltchaRegistry;
use Rasuvaeff\Yii3Altcha\AltchaRuleHandler;
use Rasuvaeff\Yii3Altcha\AltchaVerifier;
use Rasuvaeff\Yii3Altcha\ChallengeAction;
use Rasuvaeff\Yii3Altcha\ChallengeIssuer;
use Rasuvaeff\Yii3Altcha\Tests\Support\Browser;
use Rasuvaeff\Yii3Altcha\Tests\Support\RegistryReset;
use Rasuvaeff\Yii3Altcha\VerificationStatus;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    #[AfterTest]
    public function resetRegistry(): void
    {
        RegistryReset::reset();
    }

    public function paramsAreSafeByDefault(): void
    {
        $params = $this->params()['rasuvaeff/yii3-altcha'];

        Assert::same($params['secret'], '');
        Assert::true($params['replayProtection']);
        Assert::same($params['algorithm'], 'PBKDF2/SHA-256');
    }

    public function anUnsetSecretFailsTheContainerLoudly(): void
    {
        Expect::exception(\Throwable::class);

        $this->container()->get(AltchaConfig::class);
    }

    public function configuredParamsBuildAWorkingGateway(): void
    {
        $container = $this->container($this->withSecret());

        $config = $container->get(AltchaConfig::class);
        $issuer = new ChallengeIssuer($config);
        $verifier = $container->get(AltchaVerifier::class);

        Assert::same($config->algorithm, AltchaAlgorithm::Pbkdf2Sha256);
        Assert::same($verifier->verify(Browser::solve($issuer->issue())), VerificationStatus::Verified);
    }

    public function replayProtectionIsOnByDefault(): void
    {
        $container = $this->container($this->withSecret());
        $verifier = $container->get(AltchaVerifier::class);
        $payload = Browser::solve((new ChallengeIssuer($container->get(AltchaConfig::class)))->issue());

        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
        Assert::same($verifier->verify($payload), VerificationStatus::Replayed);
    }

    public function replayProtectionCanBeSwitchedOffWithoutACache(): void
    {
        $params = $this->withSecret();
        $params['rasuvaeff/yii3-altcha']['replayProtection'] = false;
        $container = $this->container($params, withCache: false);
        $payload = Browser::solve((new ChallengeIssuer($container->get(AltchaConfig::class)))->issue());

        $verifier = $container->get(AltchaVerifier::class);

        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
        Assert::same($verifier->verify($payload), VerificationStatus::Verified);
    }

    public function replayProtectionWithoutACacheFailsLoudly(): void
    {
        Expect::exception(\Throwable::class);

        $this->container($this->withSecret(), withCache: false)->get(AltchaVerifier::class);
    }

    public function theBootstrapDoesNotBuildTheVerifierSoAnUnconfiguredAppStillBoots(): void
    {
        $container = $this->container(withCache: false);

        foreach ($this->bootstrap() as $step) {
            $step($container);
        }

        Expect::exception(\Throwable::class);

        AltchaRegistry::verifier();
    }

    public function theBootstrapLooksForTheTranslatorOnlyWhenItIsAsked(): void
    {
        $inner = $this->container($this->withSecret());
        $asked = 0;
        $container = new class ($inner, $asked) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly \Psr\Container\ContainerInterface $inner, private int &$asked) {}

            public function get(string $id): mixed
            {
                return $this->inner->get($id);
            }

            public function has(string $id): bool
            {
                $this->asked++;

                return $this->inner->has($id);
            }
        };

        foreach ($this->bootstrap() as $step) {
            $step($container);
        }

        Assert::same($asked, 0);
        Assert::same(AltchaRegistry::translator(), null);
        Assert::same($asked, 1);
    }

    public function anInvalidAlgorithmParamFailsOnlyWhenTheConfigIsBuilt(): void
    {
        $params = $this->withSecret();
        $params['rasuvaeff/yii3-altcha']['algorithm'] = 'MD5';
        $container = $this->container($params);

        Expect::exception(\Throwable::class);

        $container->get(AltchaConfig::class);
    }

    public function theBootstrapFeedsTheRegistry(): void
    {
        $container = $this->container($this->withSecret());

        foreach ($this->bootstrap() as $step) {
            $step($container);
        }

        Assert::instanceOf(AltchaRegistry::verifier(), AltchaVerifier::class);
        Assert::same(AltchaRegistry::translator(), null);
    }

    public function theRuleHandlerReceivesTheTranslationCategory(): void
    {
        $handler = $this->container($this->withSecret())->get(AltchaRuleHandler::class);

        Assert::instanceOf($handler, AltchaRuleHandler::class);
    }

    public function theTranslationCategorySourceIsTagged(): void
    {
        $definition = $this->di()['yii3-altcha.categorySource'];

        Assert::same($definition['tags'], ['translation.categorySource']);
    }

    public function theChallengeActionIsAutowired(): void
    {
        $container = $this->container($this->withSecret());

        Assert::instanceOf($container->get(ChallengeAction::class), ChallengeAction::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function params(): array
    {
        return require __DIR__ . '/../config/params.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function withSecret(): array
    {
        $params = $this->params();
        $params['rasuvaeff/yii3-altcha']['secret'] = Browser::SECRET;
        $params['rasuvaeff/yii3-altcha']['cost'] = 10;
        $params['rasuvaeff/yii3-altcha']['maxCounter'] = 50;

        return $params;
    }

    /**
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    private function di(?array $params = null): array
    {
        $params ??= $this->params();

        return require __DIR__ . '/../config/di.php';
    }

    /**
     * @return list<callable(ContainerInterface): void>
     */
    private function bootstrap(): array
    {
        return require __DIR__ . '/../config/bootstrap.php';
    }

    /**
     * @param array<string, mixed>|null $params
     */
    private function container(?array $params = null, bool $withCache = true): Container
    {
        $definitions = $this->di($params);
        $psr17 = new Psr17Factory();
        $definitions[ResponseFactoryInterface::class] = $psr17;
        $definitions[StreamFactoryInterface::class] = $psr17;

        if ($withCache) {
            $definitions[CacheInterface::class] = new MemorySimpleCache();
        }

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }
}
