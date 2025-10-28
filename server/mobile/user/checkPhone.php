<?php

    /**
     * @api {post} /mobile/user/checkPhone подтвердить телефон по исходящему звонку из приложения
     * @apiVersion 1.0.0
     * @apiDescription **метод готов**
     *
     * @apiGroup User
     *
     * @apiBody {String{11}} userPhone номер телефона с кодом страны без "+"
     * @apiBody {String} deviceToken токен устройства
     * @apiBody {Number=0,1,2} platform тип клиента 0 - android, 1 - ios, 2 - web
     *
     * @apiErrorExample Ошибки
     * 401 неверный код подтверждения
     * 404 запрос не найден
     * 422 неверный формат данных
     *
     * @apiSuccess {String} accessToken токен авторизации
     * @apiSuccess {Object[]} names фамилия, имя, отчество
     * @apiSuccess {String} names.last фамилия
     * @apiSuccess {String} names.name имя
     * @apiSuccess {String} names.patronymic отчество
     */

    $headers = apache_request_headers();

    if (@$postdata['deviceToken']) {
        $device_token = "*" . $postdata['deviceToken'];
    } else
    if (@$headers['Accept-Language'] && @$headers['X-System-Info']) {
        $device_token = md5($headers['Accept-Language'] . $headers['X-System-Info']);
    } else {
        $device_token = 'default';
    }

    $user_phone = @$postdata['userPhone'];
    $platform = @$postdata['platform'] ?: '0';

    $households = loadBackend("households");
    $isdn = loadBackend("isdn");
    $inbox = loadBackend("inbox");

    $result = $isdn->checkIncoming('+' . $user_phone);

    if (strlen($user_phone) == 11 && $user_phone[0] == '7') {
        $result = $result || $isdn->checkIncoming($user_phone);
        $result = $result || $isdn->checkIncoming('8' . substr($user_phone, 1));
    }

    if ($result || in_array($user_phone, @$config["backends"]["households"]["test_numbers"] ?: [])) {
        $token = GUIDv4();
        $subscribers = $households->getSubscribers("mobile", $user_phone);
        $devices = false;
        $subscriber_id = false;
        $names = ["name" => "", "patronymic" => "", "last" => ""];
        if ($subscribers) {
            $subscriber = $subscribers[0];
            $subscriber_id = $subscriber["subscriberId"];
            $names = ["name" => $subscriber["subscriberName"], "patronymic" => $subscriber["subscriberPatronymic"], "last" => $subscriber["subscriberLast"]];
            $devices = $households->getDevices("subscriber", $subscriber_id);
        } else {
            $canAdd = @isset($config["backends"]["households"]["self_registering"]) ? : true;

            if ($canAdd) {
                $subscriber_id = $households->addSubscriber($user_phone);
            } else {
                response(403, [
                    "error" => i18n("mobile.contactProviderForAdding"),
                ]);
            }
        }

        if (!$subscriber_id) {
            response(401);
        }

        $deviceExists = false;

        if ($devices) {
            $filteredDevices = array_filter($devices, function ($device) use ($device_token) {
                return $device['deviceToken'] === $device_token;
            });
            $device = reset($filteredDevices);

            if ($device) {
                $households->modifyDevice($device["deviceId"], [ "authToken" => $token ]);
                $deviceExists = true;
            }
        }

        if (!$deviceExists) {
            if (!$households->addDevice($subscriber_id, $device_token, $platform, $token)) {
                response(403, [
                    "error" => i18n("mobile.overLimit"),
                ]);
            };
        }

        response(200, ['accessToken' => $token, 'names' => $names]);
    } else {
        response(401);
    }
