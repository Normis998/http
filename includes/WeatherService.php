<?php
class WeatherService
{
    private PDO   $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db     = $db;
        $this->config = $config;
    }

    /**
     * Главный метод: отдаёт погоду для города.
     * Всегда возвращает массив:
     *   ['success' => true, 'data' => [...], 'source' => 'cache'|'api', 'stale' => bool]
     *   ['success' => false, 'error' => '...']
     */
    public function getWeather(string $city): array
    {
        // 1. Свежий кэш?
        $cached = $this->getFromCache($city);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Идём в API
        try {
            $data = $this->fetchFromApi($city);
            $this->saveToCache($city, $data);
            return ['success' => true, 'data' => $data, 'source' => 'api'];
        } catch (Throwable $e) {
            // 3. API упал — пробуем протухший кэш
            $stale = $this->getFromCache($city, allowStale: true);
            if ($stale !== null) {
                $stale['stale'] = true;
                return $stale;
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getFromCache(string $city, bool $allowStale = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT weather_data, last_updated FROM weather_cache WHERE city = ?'
        );
        $stmt->execute([$city]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $age = time() - strtotime($row['last_updated']);
        if (!$allowStale && $age >= $this->config['cache']['lifetime']) {
            return null; // протух — пусть лучше API дёрнется
        }

        return [
            'success' => true,
            'data'    => json_decode($row['weather_data'], true),
            'source'  => 'cache',
            'age'     => $age,
        ];
    }

    private function fetchFromApi(string $city): array
    {
        $params = http_build_query([
            'q'     => $city,
            'appid' => $this->config['api']['key'],
            'units' => $this->config['api']['units'],
            'lang'  => $this->config['api']['lang'],
        ]);
        $url = $this->config['api']['base_url'] . '?' . $params;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL: $curlErr");
        }
        if ($httpCode === 401) {
            throw new RuntimeException('Неверный API-ключ OpenWeatherMap');
        }
        if ($httpCode === 404) {
            throw new RuntimeException("Город не найден: $city");
        }
        if ($httpCode !== 200) {
            throw new RuntimeException("API вернул HTTP $httpCode");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Некорректный JSON от API');
        }
        return $data;
    }

    private function saveToCache(string $city, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO weather_cache (city, weather_data, last_updated)
             VALUES (:city, :data, :updated)
             ON DUPLICATE KEY UPDATE
                weather_data = VALUES(weather_data),
                last_updated = VALUES(last_updated)'
        );
        $stmt->execute([
            ':city'    => $city,
            ':data'    => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':updated' => date('Y-m-d H:i:s'),
        ]);
    }
}