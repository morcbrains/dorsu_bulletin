<?php
if (!class_exists("Weather")) {
    class Weather {
        private $apiKey;
        private $defaultCity;
        private $units;

        public function __construct($apiKey, $defaultCity = "Mati,PH", $units = "metric") {
            $this->apiKey = $apiKey;
            $this->defaultCity = $defaultCity;
            $this->units = $units;
        }

        public function getCurrentWeather($city = "") {
            if (empty($city)) {
                $city = $this->defaultCity;
            }

            if (empty($this->apiKey) || $this->apiKey === "PASTE_YOUR_OPENWEATHER_API_KEY_HERE") {
                return [
                    "success" => false,
                    "message" => "OpenWeather API key is not configured yet."
                ];
            }

            $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) .
                   "&appid=" . urlencode($this->apiKey) .
                   "&units=" . urlencode($this->units);

            $response = $this->makeRequest($url);

            if (!$response["success"]) {
                return $response;
            }

            $data = json_decode($response["body"], true);

            if (!$data || !isset($data["cod"])) {
                return [
                    "success" => false,
                    "message" => "Invalid weather response."
                ];
            }

            if (intval($data["cod"]) !== 200) {
                $errorMessage = isset($data["message"]) ? $data["message"] : "Unable to fetch weather data.";

                return [
                    "success" => false,
                    "message" => ucfirst($errorMessage)
                ];
            }

            $weatherMain = isset($data["weather"][0]["main"]) ? $data["weather"][0]["main"] : "Weather";
            $weatherDescription = isset($data["weather"][0]["description"]) ? $data["weather"][0]["description"] : "No description";
            $weatherIcon = isset($data["weather"][0]["icon"]) ? $data["weather"][0]["icon"] : "";
            $temperature = isset($data["main"]["temp"]) ? round($data["main"]["temp"]) : "N/A";
            $feelsLike = isset($data["main"]["feels_like"]) ? round($data["main"]["feels_like"]) : "N/A";
            $humidity = isset($data["main"]["humidity"]) ? $data["main"]["humidity"] : "N/A";
            $windSpeed = isset($data["wind"]["speed"]) ? $data["wind"]["speed"] : "N/A";
            $locationName = isset($data["name"]) ? $data["name"] : $city;
            $country = isset($data["sys"]["country"]) ? $data["sys"]["country"] : "";

            return [
                "success" => true,
                "city" => $locationName,
                "country" => $country,
                "main" => $weatherMain,
                "description" => ucwords($weatherDescription),
                "icon" => $weatherIcon,
                "temperature" => $temperature,
                "feels_like" => $feelsLike,
                "humidity" => $humidity,
                "wind_speed" => $windSpeed,
                "unit_symbol" => $this->units === "metric" ? "°C" : "°F"
            ];
        }

        private function makeRequest($url) {
            if (function_exists("curl_init")) {
                $curl = curl_init();

                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

                $body = curl_exec($curl);
                $error = curl_error($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                curl_close($curl);

                if ($body === false || !empty($error)) {
                    return [
                        "success" => false,
                        "message" => "Weather request failed: " . $error
                    ];
                }

                if ($httpCode < 200 || $httpCode >= 300) {
                    return [
                        "success" => false,
                        "message" => "Weather request failed with HTTP code " . $httpCode
                    ];
                }

                return [
                    "success" => true,
                    "body" => $body
                ];
            }

            $context = stream_context_create([
                "http" => [
                    "timeout" => 10
                ]
            ]);

            $body = @file_get_contents($url, false, $context);

            if ($body === false) {
                return [
                    "success" => false,
                    "message" => "Weather request failed. Please enable cURL or allow_url_fopen."
                ];
            }

            return [
                "success" => true,
                "body" => $body
            ];
        }

        public function getIconUrl($iconCode) {
            if (empty($iconCode)) {
                return "";
            }

            return "https://openweathermap.org/img/wn/" . $iconCode . "@2x.png";
        }
    }
}
?>