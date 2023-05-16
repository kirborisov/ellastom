<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;


return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    $handlerIdent = function (Request $request, Response $response, $args) {
        $logDir = __DIR__.'/../logs';

        // Получаем данные заголовков
        $headers = $request->getHeaders();
        // Получаем тело запроса
        $body = (string)$request->getBody();
        // Получаем IP-адрес
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'];
        // Получаем текущее время
        $currentTime = date("Y-m-d H:i:s");
        // Получаем данные авторизации
        $authData = $request->getHeaderLine('Authorization');



        // Если в теле запроса содержится JSON или XML, сохраняем его как файл
        $fileName = '';
        $contentType = $request->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') !== false) {
            // Записываем JSON в файл
            $fileName = time().'.json';
            file_put_contents($logDir.'/json_logs/'.$fileName, $body);
        } elseif (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
            // Записываем XML в файл
            $fileName = time().'.xml';
            file_put_contents($logDir.'/xml_logs/'.$fileName, $body);
        }
        
        // Записываем в логи
        $logEntry = array(
            "Time" => $currentTime,
            "IP Address" => $ipAddress,
            "Headers" => $headers,
            "Authorization" => $authData,
            "Body" => $body,
            "POST" => $_POST,
            "GET" => $_GET,
            "SERVER" => $_SERVER,
            "fileName" => $fileName,
        );

        file_put_contents($logDir.'/logs.txt', json_encode($logEntry)."\n\n\n\n", FILE_APPEND);

        // Отправляем ответ
        //$response->getBody()->write('Ok');

        $data = array('answer' => 'ok');
        $payload = json_encode($data);

        $response->getBody()->write($payload);
        return $response
               ->withHeader('Content-Type', 'application/json')
               ->withStatus(200);

    };
    $app->get('/ident[/{params:.*}]', $handlerIdent);
    $app->post('/ident[/{params:.*}]', $handlerIdent);

};

