<?php
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'weather_app',
        'user'    => 'weather_user',
        'pass'    => 'weather_pass_2026',
        'charset' => 'utf8mb4',
    ],
    'api' => [
        'key'      => '6f4592d24e2fec9f4558e7de35d53983',
        'base_url' => 'https://api.openweathermap.org/data/2.5/weather',
        'units'    => 'metric',
        'lang'     => 'ru',
    ],
    'cache' => [
        'lifetime' => 1800, // 30 минут в секундах
    ],
    'default_city' => 'Moscow',
    // en-название (для API) => ru-название (для отображения)
    'cities' => [
        'Moscow'           => 'Москва',
        'Saint Petersburg' => 'Санкт-Петербург',
        'Novosibirsk'      => 'Новосибирск',
        'Yekaterinburg'    => 'Екатеринбург',
        'Kazan'            => 'Казань',
    ],
];