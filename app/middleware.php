<?php
declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use Slim\App;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;


class IdentKeyMiddleware
{
    /**
     * @var string
     */
    private $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (strpos($request->getUri()->getPath(), '/ident/') === 0) {
            $key = $request->getHeaderLine('IDENT-Integration-Key');
            if ($key !== $this->key) {
                return new Response(200);
            }
        }

        return $handler->handle($request);
    }
}



return function (App $app) {
    $app->add(SessionMiddleware::class);
};
