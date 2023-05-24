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
        setlocale(LC_TIME, 'ru_RU.UTF-8'); // Установить локаль для корректного вывода на русском языке

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
                'StartDateTime' => date("Y-m-d\TH:i:sP", strtotime($item['StartDateTime'])),
                'strDateTime' => strftime('%e %B %Y %H:%M', strtotime($item['StartDateTime'])),
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

        $stmt = $this->get('db')->prepare("SELECT Doctors.Id, Doctors.Name, DoctorSite.jobs, image FROM Doctors 
                                            JOIN DoctorSite ON Doctors.Id = DoctorSite.DoctorId
                                            ORDER BY DoctorSite.sorted");
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //
        // Get the data from the database
        $stmt = $this->get('db')->prepare("SELECT Intervals.StartDateTime, Intervals.IsBusy, Doctors.Id 
                                            FROM Doctors JOIN Intervals ON Doctors.Id = Intervals.DoctorId 
                                            
                                            WHERE Intervals.StartDateTime >= CURDATE() ORDER BY Intervals.StartDateTime");
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
        $data = array();
        $payload = json_encode($data);

        $response->getBody()->write($payload);
        return $response
               ->withHeader('Content-Type', 'application/json')
               ->withStatus(200);
    };

    //$app->get('/ident/GetTickets[/{params:.*}]', $handlerIdent);

    $app->get('/ident/GetTickets[/{params:.*}]', function (Request $request, Response $response, array $args) {
        $pdo = $this->get('db');
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT IdUUID as Id,
                        DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, "+03:00"), "%Y-%m-%dT%H:%i:%s+03:00") as DateAndTime, 
                        DATE_FORMAT(CONVERT_TZ(DateAndTime, @@session.time_zone, "+03:00"), "%Y-%m-%dT%H:%i:%s+03:00") as PlanStart, 
                        ClientPhone, ClientFullName, DoctorId, "Онлайн запись" as FormName FROM Appointment WHERE Status = 0');
            $stmt->execute();
            $data = $stmt->fetchAll();

            $data = [[
                  "Id"=>14000,
                  "DateAndTime"=>"2023-05-24T18:41:15+03:00",
                  "FormName"=>"Онлайн запись",
                  "PlanStart"=>"2023-06-13T19:00:00+03:00",
                  "ClientPhone"=>"+79889860067",
                  "DoctorId"=>"11",
                  "DoctorName"=>"Крамаренко Александр Владимирович",
                  "ClientFullName"=>"Александр Вольф",
                  "HttpReferer"=>""
                ]];

            $response->getBody()->write(json_encode($data));

            $stmt = $pdo->prepare('UPDATE Appointment SET Status=1 WHERE Status = 0');
            $stmt->execute();

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            $response->getBody()->write("Failed: " . $e->getMessage());
            return $response->withStatus(500);
        }

        return $response->withHeader('Content-Type', 'application/json');
    });


    $app->post('/ident[/{params:.*}]', function (Request $request, Response $response) {

        $data = $request->getBody()->getContents();
        $data = json_decode($data, true);
        
       $pdo = $this->get('db'); 
        try {
            $pdo->beginTransaction();

            $pdo->exec("DELETE FROM Intervals");

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


    $app->post('/appointment[/{params:.*}]', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $stmt = $this->get('db')->prepare("INSERT INTO Appointment (IdUUID, DateAndTime, ClientPhone, ClientFullName, DoctorId, Status)
                                    VALUES( '".$data['Id']."', '".$data['DateAndTime']."', '".$data['ClientPhone']."', '".$data['ClientFullName']."', ".$data['DoctorId'].", 0 )");
        
        $stmt->execute();
        $data = $data;
        $payload = json_encode($data);

        $response->getBody()->write($payload);
        return $response
               ->withHeader('Content-Type', 'application/json')
               ->withStatus(200);
    });

};

