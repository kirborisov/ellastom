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

    /*
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });
    */

    // Define a route
    $app->get('/', function ($request, $response, $args) {
        return $this->get('view')->render($response, 'home.twig');
    });

    $app->get('/документы[/{params:.*}]', function ($request, $response, $args) {
        return $this->get('view')->render($response, 'documents.twig');
    });

    $app->get('/адрес[/{params:.*}]', function ($request, $response, $args) {
        return $this->get('view')->render($response, 'address.twig');
    });

    $app->get('/до-и-после[/{params:.*}]', function ($request, $response, $args) {
        return $this->get('view')->render($response, 'before_after.twig');
    });

    $app->get('/прайс[/{params:.*}]', function ($request, $response, $args) {
        return $this->get('view')->render($response, 'price.twig');
    });

    function getDoctorsTime($schedule) {

        $doctorsTime = [];

        // Создаем массив последних дат для каждого врача и массив расписания врачей
        $lastDates = [];
        $scheduleData = [];
        foreach ($schedule as $item) {
            if ($item['IsBusy']) {
                continue;
            }
            // Форматируем дату в нужный формат
            $date = strftime("%Y-%m-%d", strtotime($item['StartDateTime']));
            $doctorId = $item['Id'];

            if (empty($lastDates[$doctorId]) || strtotime($date) > strtotime($lastDates[$doctorId])) {
                $lastDates[$doctorId] = $date;
            }
            $scheduleData[$doctorId][$date][] = [
                'start' => date("H:i", strtotime($item['StartDateTime'])),
            ];
        }

        // Проходимся по каждому врачу
        foreach ($lastDates as $doctorId => $lastDate) {
            $date = date("Y-m-d");
            while (strtotime($date) <= strtotime($lastDate)) {
                $formattedDate = strftime("%Y-%m-%d", strtotime($date));
                $formattedDateOut = strftime("%a \n%d %B", strtotime($date));
                $doctorsTime[$doctorId][$formattedDateOut] = $scheduleData[$doctorId][$formattedDate] ?? [];
                $date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
            }
        }

        return $doctorsTime;
    }
    
    function getNearDateOfDoctor($doctorsTime) {
        // Найти ближайшую дату и время для каждого доктора
        $nearDateOfDoctor = [];
        foreach($doctorsTime as $doctorId => $dataOfDate) {
            foreach($dataOfDate as $date => $times) {
                if(isset($times[0])) {
                    $nearDateOfDoctor[$doctorId] = ['date'=>$date, 'time'=>$times[0]['start']];
                    break;
                }
            }
        }
        return $nearDateOfDoctor;

    }

    $app->get('/наш-персонал[/{params:.*}]', function ($request, $response, $args) {
        setlocale(LC_TIME, 'ru_RU.UTF-8'); // Установить локаль для корректного вывода на русском языке

        $stmt = $this->get('db')->prepare("SELECT * FROM DoctorSite JOIN Doctors ON DoctorSite.DoctorId = Doctors.Id");
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //
        // Get the data from the database
        $stmt = $this->get('db')->prepare("SELECT Intervals.StartDateTime, Intervals.IsBusy, Doctors.Id FROM Doctors JOIN Intervals ON Doctors.Id = Intervals.DoctorId WHERE Intervals.StartDateTime >= CURDATE() ORDER BY Intervals.StartDateTime");
        $stmt->execute();
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $doctorsTime = getDoctorsTime($schedule);
        $nearDateOfDoctor = getNearDateOfDoctor($doctorsTime);

        return $this->get('view')->render($response, 'online_record.twig', ['doctors'=>$doctors, 'doctorsTime'=>$doctorsTime, 'nearDateOfDoctor'=>$nearDateOfDoctor]);
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
    //$app->get('/ident[/{params:.*}]', $handlerIdent);
    //$app->post('/ident[/{params:.*}]', $handlerIdent);


    $app->post('/ident[/{params:.*}]', function (Request $request, Response $response) {
        $data = $request->getBody()->getContents();
        $data = json_decode($data, true);
        
       $pdo = $this->get('db'); 
        try {
            $pdo->beginTransaction();

            $pdo->exec("DELETE FROM Intervals");
            $pdo->exec("DELETE FROM Doctors");
            $pdo->exec("DELETE FROM Branches");

            $stmtDoctor = $pdo->prepare("INSERT INTO Doctors (Id, Name) VALUES (?, ?)");
            foreach ($data['Doctors'] as $doctor) {
                $stmtDoctor->execute([$doctor['Id'], $doctor['Name']]);
            }

            $stmtBranch = $pdo->prepare("INSERT INTO Branches (Id, Name) VALUES (?, ?)");
            foreach ($data['Branches'] as $branch) {
                $stmtBranch->execute([$branch['Id'], $branch['Name']]);
            }

            $stmtInterval = $pdo->prepare("INSERT INTO Intervals (DoctorId, BranchId, StartDateTime, LengthInMinutes, IsBusy) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['Intervals'] as $interval) {
                $stmtInterval->execute([$interval['DoctorId'], $interval['BranchId'], $interval['StartDateTime'], $interval['LengthInMinutes'], $interval['IsBusy']]);
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $response->getBody()->write("Failed: " . $e->getMessage());
            return $response->withStatus(500);
        }

        $data = array('answer' => 'ok');
        $payload = json_encode($data);

        $response->getBody()->write($payload);
        return $response
               ->withHeader('Content-Type', 'application/json')
               ->withStatus(200);
    });


    $app->get('/time', function (Request $request, Response $response, array $args) {


        $response->getBody()->write($html);
        return $response;
    });


};

