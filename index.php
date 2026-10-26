<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/WeatherService.php';

$config = require __DIR__ . '/config.php';

$db      = getDb($config['db']);
$service = new WeatherService($db, $config);

$selectedCity = trim($_GET['city'] ?? $config['default_city']);
$result = $service->getWeather($selectedCity);

// Логика недавних городов
$recentCities = [];
if (isset($_COOKIE['recent_cities'])) {
    $recentCities = json_decode($_COOKIE['recent_cities'], true) ?: [];
}
if (isset($_GET['city']) && $result['success']) {
    $actualCityName = $result['data']['name']; 
    $recentCities = array_filter($recentCities, function($c) use ($actualCityName) {
        return strcasecmp($c, $actualCityName) !== 0;
    });
    array_unshift($recentCities, $actualCityName);
    $recentCities = array_slice($recentCities, 0, 5);
    setcookie('recent_cities', json_encode($recentCities), time() + (86400 * 30), "/");
}

function getCityDisplayName($city, $config) {
    if (isset($config['cities'][$city])) return $config['cities'][$city];
    return htmlspecialchars($city);
}

function getWindDirection($deg) {
    $directions = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];
    return $directions[round($deg / 45) % 8];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Погода — <?= htmlspecialchars($selectedCity) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="logo">Weather Dash</div>
            
            <form method="GET" action="" class="search-form">
                <input type="text" name="city" placeholder="Поиск (на англ.)" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>" required>
                <button type="submit">Найти</button>
            </form>

            <?php if (!empty($recentCities)): ?>
                <div class="city-list">
                    <p>Недавние:</p>
                    <?php foreach ($recentCities as $rc): ?>
                        <?php $isActive = (strcasecmp($rc, $result['data']['name'] ?? '') === 0); ?>
                        <a href="?city=<?= urlencode($rc) ?>" class="city-btn <?= $isActive ? 'active' : '' ?>">
                            <?= getCityDisplayName($rc, $config) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="city-list">
                <p>Популярные:</p>
                <?php foreach ($config['cities'] as $en => $ru): ?>
                    <a href="?city=<?= urlencode($en) ?>" class="city-btn <?= $en === $selectedCity ? 'active' : '' ?>">
                        <?= htmlspecialchars($ru) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="main-content">
            <?php if (!$result['success']): ?>
                <div class="error-card">
                    <h2>Город не найден 😔</h2>
                    <p><?= htmlspecialchars($result['error']) ?></p>
                </div>
            <?php else: 
                $d = $result['data'];
                $temp = round($d['main']['temp']);
                $feels = round($d['main']['feels_like']);
                $desc = ucfirst($d['weather'][0]['description']);
                $icon = $d['weather'][0]['icon'];
                
                // Данные для графиков
                $humidity = $d['main']['humidity'];
                $wind = $d['wind']['speed'];
                $windDeg = $d['wind']['deg'] ?? 0;
                $pressure = round($d['main']['pressure'] * 0.75006);
                $clouds = $d['clouds']['all'];
                $visibility = isset($d['visibility']) ? round($d['visibility'] / 1000, 1) : 10;
                
                // Время и Солнце
                $timezone = $d['timezone'];
                $sunrise = gmdate("H:i", $d['sys']['sunrise'] + $timezone);
                $sunset = gmdate("H:i", $d['sys']['sunset'] + $timezone);
                
                $currentTime = time();
                $isDay = ($currentTime >= $d['sys']['sunrise'] && $currentTime <= $d['sys']['sunset']);
                
                // Расчет процентов для CSS-графиков
                $humidityPercent = $humidity;
                $cloudsPercent = $clouds;
                $windPercent = min(round(($wind / 20) * 100), 100); // 20 м/с = 100%
                $pressurePercent = min(max(round((($pressure - 700) / 100) * 100), 0), 100); // 700-800 мм
                $visPercent = min(round(($visibility / 10) * 100), 100); // 10 км = 100%
                
                // Прогресс дня (для дуги солнца)
                $dayLength = $d['sys']['sunset'] - $d['sys']['sunrise'];
                $dayProgress = 0;
                if ($currentTime > $d['sys']['sunrise'] && $currentTime < $d['sys']['sunset']) {
                    $dayProgress = round((($currentTime - $d['sys']['sunrise']) / $dayLength) * 100);
                } elseif ($currentTime >= $d['sys']['sunset']) {
                    $dayProgress = 100;
                }

                $iconUrl = "https://openweathermap.org/img/wn/{$icon}@4x.png";
            ?>

            <div class="weather-hero">
                <h1 class="hero-city"><?= htmlspecialchars($d['name']) ?>, <?= htmlspecialchars($d['sys']['country']) ?></h1>
                <div class="hero-main">
                    <img src="<?= $iconUrl ?>" alt="<?= $desc ?>" class="hero-icon">
                    <div class="hero-temp"><?= $temp ?>°</div>
                </div>
                <div class="hero-desc"><?= $desc ?></div>
                <div class="hero-feels">Ощущается как <?= $feels ?>°</div>
            </div>

            <div class="widgets-grid">
                <!-- Влажность (Круговая диаграмма) -->
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-icon">💧</span>
                        <span class="widget-label">Влажность</span>
                    </div>
                    <div class="chart-container">
                        <div class="circle-chart" style="--percent: <?= $humidityPercent ?>%;">
                            <div class="circle-inner"><?= $humidity ?>%</div>
                        </div>
                    </div>
                </div>
                
                <!-- Ветер (Компас + полоса) -->
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-icon">💨</span>
                        <span class="widget-label">Ветер</span>
                    </div>
                    <div class="wind-container">
                        <div class="compass">
                            <div class="arrow" style="transform: rotate(<?= $windDeg ?>deg);">⬆</div>
                        </div>
                        <div class="wind-info">
                            <div class="widget-value"><?= $wind ?> <span class="widget-sub">м/с</span></div>
                            <div class="widget-sub"><?= getWindDirection($windDeg) ?></div>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $windPercent ?>%;"></div>
                    </div>
                </div>
                
                <!-- Давление (Горизонтальная шкала) -->
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-icon">📊</span>
                        <span class="widget-label">Давление</span>
                    </div>
                    <div class="widget-value"><?= $pressure ?> <span class="widget-sub">мм рт. ст.</span></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $pressurePercent ?>%;"></div>
                    </div>
                    <div class="progress-labels">
                        <span>Низкое</span>
                        <span>Норма</span>
                        <span>Высокое</span>
                    </div>
                </div>

                <!-- Облачность (Круговая диаграмма) -->
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-icon">☁️</span>
                        <span class="widget-label">Облачность</span>
                    </div>
                    <div class="chart-container">
                        <div class="circle-chart" style="--percent: <?= $cloudsPercent ?>%;">
                            <div class="circle-inner"><?= $clouds ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- Видимость (Шкала) -->
                <div class="widget">
                    <div class="widget-header">
                        <span class="widget-icon">👁️</span>
                        <span class="widget-label">Видимость</span>
                    </div>
                    <div class="widget-value"><?= $visibility ?> <span class="widget-sub">км</span></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $visPercent ?>%;"></div>
                    </div>
                </div>

                <!-- Восход и Закат (Дуга дня) -->
                <div class="widget double">
                    <div class="widget-header">
                        <span class="widget-icon"><?= $isDay ? '☀️' : '🌙' ?></span>
                        <span class="widget-label">Световой день</span>
                    </div>
                    <div class="sun-arc-container">
                        <div class="sun-times-labels">
                            <span><?= $sunrise ?></span>
                            <span><?= $sunset ?></span>
                        </div>
                        <div class="arc-track">
                            <div class="arc-progress" style="width: <?= $dayProgress ?>%;">
                                <div class="sun-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>