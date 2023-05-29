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

    $app->add(new IdentKeyMiddleware('q5qt8f0te5mfsgerg'));

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

            $queryParams = $request->getQueryParams();

            $dateTimeFrom = $queryParams['dateTimeFrom'] ?? null;
            $dateTimeTo = $queryParams['dateTimeTo'] ?? null;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id as Id,
                        DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, "+03:00"), "%Y-%m-%dT%H:%i:%s+03:00") as DateAndTime, 
                        DATE_FORMAT(CONVERT_TZ(DateAndTime, @@session.time_zone, "+03:00"), "%Y-%m-%dT%H:%i:%s+03:00") as PlanStart, 
                        ClientPhone, ClientFullName, DoctorId, "Онлайн запись" as FormName FROM Appointment WHERE created_at BETWEEN :dateTimeFrom AND :dateTimeTo');
            $stmt->execute(['dateTimeFrom' => $dateTimeFrom, 'dateTimeTo' => $dateTimeTo]);
            $data = $stmt->fetchAll();

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


    $app->post('/ident/PostTimeTable[/{params:.*}]', function (Request $request, Response $response) {

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

    function sendMail($data) {
        $subject = 'Стоматология <Элла-стом> новая запись';
        $message = "Имя: ".$data['ClientFullName']."\nТелефон: ".$data['ClientPhone']."\nДата: ".$data['DateAndTime']."\nДоктор: ".$data['DoctorName'];
        mail('silvervola@mail.ru', $subject, $message);
        mail('ella-stom@yandex.ru', $subject, $message);
        mail('kramarenkokra@mail.ru', $subject, $message);
    }

    //$app->get('/sendSMS', function (Request $request, Response $response, array $args) {
    function sendSms($phone) {
        // если нужно отключить sms валидацию
        // return false;

        $code = rand(1000, 9999); // генерируем случайный код
        $message = 'Код для записи: '. $code;

        // Ваши данные для авторизации в сервисе redsms
        $login = 'ella-stom';
        $apiKey = 'PJswrjplpkVqcMZnkPKuqOcg';
        $ts = 'wefwfe^&^7t76T&y';
        
        // Сформируем строку запроса
        $api = 'message';
        $params = [
            "from" => "Ella-Stom",
            "route" => 'sms',
            "text" => $message,
            "to" => $phone
        ];
        ksort($params);
        reset($params);

        $token = md5($ts . $apiKey);
        
        $params["login"] = $login;
        $params["ts"] = $ts;
        $params["secret"] = $token;

        // Отправка SMS
        $ch = curl_init("https://cp.redsms.ru/api/{$api}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $output = json_decode(curl_exec($ch), true);
        curl_close($ch); 
        
        if(isset($output['success']) and $output['success'] == true){
            return $code;
        } else{ 
            return false;

        }
    }

    function checkValidCodeSms($pdo, $phone, $code) {
        $stmt = $pdo->prepare('SELECT * FROM PhoneVerification WHERE phone = :phone AND code = :code');
        $stmt->execute(['phone' => $phone, 'code' => $code]);
        $res = $stmt->fetch();
        if($res) {
            $stmt = $pdo->prepare('UPDATE PhoneVerification SET valid=TRUE WHERE id=:id');                                                
            $stmt->execute(['id' => $res['id']]);
        }

        return $res;
    }

    function insertCodeDB($pdo, $phone, $code, $ipAddress) {
        // Подготавливаем SQL-запрос
        $stmt = $pdo->prepare('INSERT INTO PhoneVerification (phone, code, ip) VALUES (:phone, :code, :ip)');

        // Задаем значения для параметров запроса
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':ip', $ipAddress);

        // Исполняем запрос
        $stmt->execute();
        
    }

    function checkBadIpAddress($pdo, $ip) {
        $stmt = $pdo->prepare('SELECT * FROM PhoneVerification WHERE ip = :ip AND created_at >= NOW() - INTERVAL 1 MINUTE');
        $stmt->execute(['ip' => $ip]);
        return $stmt->fetch();
        
    }

    function checkGoodPhone($pdo, $phone) {
        $stmt = $pdo->prepare('SELECT * FROM PhoneVerification WHERE phone = :phone AND valid = TRUE');
        $stmt->execute(['phone' => $phone]);
        return $stmt->fetch();
        
    }

    function addAppointment($pdo, $data) {
        $stmt = $pdo->prepare("INSERT INTO Appointment (IdUUID, DateAndTime, ClientPhone, ClientFullName, DoctorId, Status)
                                    VALUES( :Id, :DateAndTime, :ClientPhone, :ClientFullName, :DoctorId, 0 )");
        $stmt->bindParam(':Id', $data['Id']);
        $stmt->bindParam(':DateAndTime', $data['DateAndTime']);
        $stmt->bindParam(':ClientPhone', $data['ClientPhone']);
        $stmt->bindParam(':ClientFullName', $data['ClientFullName']);
        $stmt->bindParam(':DoctorId', $data['DoctorId']);

        sendMail($data);
        $stmt->execute();
        $res = json_encode(["type"=>"success"]);
        return $res;
    
    }

    $app->post('/appointment[/{params:.*}]', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'];
        $phone = $data['ClientPhone'];
        $pdo = $this->get('db');

        if(checkGoodPhone($pdo, $phone)) {
            $res = addAppointment($pdo, $data);

        } elseif(checkBadIpAddress($pdo, $ipAddress) && $data['sended_sms'] == 'no') {
            $res = json_encode(["type"=>"bad_ip"]);
        } elseif($data['sended_sms'] == 'no') {
            $code = sendSms($phone);
            insertCodeDB($pdo, $phone, $code, $ipAddress);
            $res = json_encode(["type"=>"sms"]);

        } else {
            if(checkValidCodeSms($pdo, $phone, $data['code_phone'])) {
                $res = addAppointment($pdo, $data);
                    
            } else {
                $res = json_encode(["type"=>"bad_code"]);
            }
        }

        $response->getBody()->write($res);

        return $response
               ->withHeader('Content-Type', 'application/json')
               ->withStatus(200);

    });



};

