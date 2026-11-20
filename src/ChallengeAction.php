<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Altcha;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves a fresh challenge as JSON; the v3 widget fetches it through its `challenge` attribute.
 *
 * @api
 */
final readonly class ChallengeAction implements RequestHandlerInterface
{
    public function __construct(
        private ChallengeIssuer $issuer,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_encode($this->issuer->issue(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
