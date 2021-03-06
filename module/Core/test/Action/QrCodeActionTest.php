<?php
declare(strict_types=1);

namespace ShlinkioTest\Shlink\Core\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Server\RequestHandlerInterface;
use Shlinkio\Shlink\Common\Response\QrCodeResponse;
use Shlinkio\Shlink\Core\Action\QrCodeAction;
use Shlinkio\Shlink\Core\Entity\ShortUrl;
use Shlinkio\Shlink\Core\Exception\EntityDoesNotExistException;
use Shlinkio\Shlink\Core\Exception\InvalidShortCodeException;
use Shlinkio\Shlink\Core\Service\UrlShortener;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Router\RouterInterface;

class QrCodeActionTest extends TestCase
{
    /** @var QrCodeAction */
    private $action;
    /** @var ObjectProphecy */
    private $urlShortener;

    public function setUp(): void
    {
        $router = $this->prophesize(RouterInterface::class);
        $router->generateUri(Argument::cetera())->willReturn('/foo/bar');

        $this->urlShortener = $this->prophesize(UrlShortener::class);

        $this->action = new QrCodeAction($router->reveal(), $this->urlShortener->reveal());
    }

    /** @test */
    public function aNotFoundShortCodeWillDelegateIntoNextMiddleware()
    {
        $shortCode = 'abc123';
        $this->urlShortener->shortCodeToUrl($shortCode)->willThrow(EntityDoesNotExistException::class)
                                                       ->shouldBeCalledOnce();
        $delegate = $this->prophesize(RequestHandlerInterface::class);
        $process = $delegate->handle(Argument::any())->willReturn(new Response());

        $this->action->process((new ServerRequest())->withAttribute('shortCode', $shortCode), $delegate->reveal());

        $process->shouldHaveBeenCalledOnce();
    }

    /** @test */
    public function anInvalidShortCodeWillReturnNotFoundResponse()
    {
        $shortCode = 'abc123';
        $this->urlShortener->shortCodeToUrl($shortCode)->willThrow(InvalidShortCodeException::class)
                                                       ->shouldBeCalledOnce();
        $delegate = $this->prophesize(RequestHandlerInterface::class);
        $process = $delegate->handle(Argument::any())->willReturn(new Response());

        $this->action->process((new ServerRequest())->withAttribute('shortCode', $shortCode), $delegate->reveal());

        $process->shouldHaveBeenCalledOnce();
    }

    /** @test */
    public function aCorrectRequestReturnsTheQrCodeResponse()
    {
        $shortCode = 'abc123';
        $this->urlShortener->shortCodeToUrl($shortCode)->willReturn(new ShortUrl(''))
                                                       ->shouldBeCalledOnce();
        $delegate = $this->prophesize(RequestHandlerInterface::class);

        $resp = $this->action->process(
            (new ServerRequest())->withAttribute('shortCode', $shortCode),
            $delegate->reveal()
        );

        $this->assertInstanceOf(QrCodeResponse::class, $resp);
        $this->assertEquals(200, $resp->getStatusCode());
        $delegate->handle(Argument::any())->shouldHaveBeenCalledTimes(0);
    }
}
