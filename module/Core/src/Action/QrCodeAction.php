<?php
declare(strict_types=1);

namespace Shlinkio\Shlink\Core\Action;

use Endroid\QrCode\QrCode;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shlinkio\Shlink\Common\Response\QrCodeResponse;
use Shlinkio\Shlink\Core\Action\Util\ErrorResponseBuilderTrait;
use Shlinkio\Shlink\Core\Exception\EntityDoesNotExistException;
use Shlinkio\Shlink\Core\Exception\InvalidShortCodeException;
use Shlinkio\Shlink\Core\Service\UrlShortenerInterface;
use Zend\Expressive\Router\Exception\RuntimeException;
use Zend\Expressive\Router\RouterInterface;

class QrCodeAction implements MiddlewareInterface
{
    use ErrorResponseBuilderTrait;

    private const DEFAULT_SIZE = 300;
    private const MIN_SIZE = 50;
    private const MAX_SIZE = 1000;

    /** @var RouterInterface */
    private $router;
    /** @var UrlShortenerInterface */
    private $urlShortener;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        RouterInterface $router,
        UrlShortenerInterface $urlShortener,
        LoggerInterface $logger = null
    ) {
        $this->router = $router;
        $this->urlShortener = $urlShortener;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param Request $request
     * @param RequestHandlerInterface $handler
     *
     * @return Response
     * @throws \InvalidArgumentException
     * @throws RuntimeException
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Make sure the short URL exists for this short code
        $shortCode = $request->getAttribute('shortCode');
        try {
            $this->urlShortener->shortCodeToUrl($shortCode);
        } catch (InvalidShortCodeException | EntityDoesNotExistException $e) {
            $this->logger->warning('An error occurred while creating QR code. {e}', ['e' => $e]);
            return $this->buildErrorResponse($request, $handler);
        }

        $path = $this->router->generateUri('long-url-redirect', ['shortCode' => $shortCode]);
        $size = $this->getSizeParam($request);

        $qrCode = new QrCode((string) $request->getUri()->withPath($path)->withQuery(''));
        $qrCode->setSize($size)
               ->setPadding(0);
        return new QrCodeResponse($qrCode);
    }

    /**
     * @param Request $request
     * @return int
     */
    private function getSizeParam(Request $request): int
    {
        $size = (int) $request->getAttribute('size', self::DEFAULT_SIZE);
        if ($size < self::MIN_SIZE) {
            return self::MIN_SIZE;
        }

        return $size > self::MAX_SIZE ? self::MAX_SIZE : $size;
    }
}
