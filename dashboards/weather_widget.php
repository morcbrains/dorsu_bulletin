<?php
require_once __DIR__ . "/../config/openweather.php";
require_once __DIR__ . "/../classes/Weather.php";

$weatherObj = new Weather(
    OPENWEATHER_API_KEY,
    OPENWEATHER_DEFAULT_CITY,
    OPENWEATHER_UNITS
);

$weatherData = $weatherObj->getCurrentWeather();
?>

<style>
    .weather-card {
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.20), transparent 35%),
            linear-gradient(135deg, #002b7f, #003fae, #f6c400);
        color: white;
        padding: 24px;
        border-radius: 22px;
        box-shadow: 0 14px 35px rgba(0, 43, 127, 0.22);
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(246, 196, 0, 0.45);
    }

    .weather-card::before {
        content: "";
        position: absolute;
        width: 190px;
        height: 190px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        top: -70px;
        right: -50px;
    }

    .weather-card::after {
        content: "";
        position: absolute;
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: rgba(246, 196, 0, 0.18);
        bottom: -50px;
        left: -45px;
    }

    .weather-content {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 20px;
        align-items: center;
    }

    .weather-label {
        display: inline-block;
        background: rgba(255, 247, 204, 0.22);
        color: #fff7cc;
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 13px;
        border: 1px solid rgba(255, 247, 204, 0.35);
    }

    .weather-card h2 {
        font-size: 28px;
        margin-bottom: 7px;
        color: white;
    }

    .weather-card p {
        line-height: 1.6;
        opacity: 0.95;
        color: white;
    }

    .weather-main {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 18px;
    }

    .weather-main img {
        width: 82px;
        height: 82px;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 18px;
        border: 1px solid rgba(255, 247, 204, 0.40);
    }

    .weather-temp {
        text-align: right;
    }

    .weather-temp h3 {
        font-size: 45px;
        margin-bottom: 4px;
        color: white;
    }

    .weather-temp span {
        font-size: 14px;
        opacity: 0.95;
        color: #fff7cc;
        font-weight: bold;
    }

    .weather-details {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .weather-pill {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 247, 204, 0.35);
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: bold;
        color: white;
    }

    .weather-error {
        background: white;
        color: #002b7f;
        padding: 18px;
        border-radius: 18px;
        margin-bottom: 25px;
        border-left: 7px solid #f6c400;
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
        font-weight: bold;
    }

    @media (max-width: 760px) {
        .weather-content {
            grid-template-columns: 1fr;
        }

        .weather-main {
            justify-content: flex-start;
        }

        .weather-temp {
            text-align: left;
        }
    }
</style>

<?php if ($weatherData["success"]): ?>
    <section class="weather-card">
        <div class="weather-content">
            <div>
                <span class="weather-label">Campus Weather</span>

                <h2>
                    <?php echo htmlspecialchars($weatherData["city"]); ?>
                    <?php echo !empty($weatherData["country"]) ? ", " . htmlspecialchars($weatherData["country"]) : ""; ?>
                </h2>

                <p>
                    Current condition:
                    <strong><?php echo htmlspecialchars($weatherData["description"]); ?></strong>
                </p>

                <div class="weather-details">
                    <span class="weather-pill">
                        Feels like: <?php echo htmlspecialchars($weatherData["feels_like"]); ?><?php echo htmlspecialchars($weatherData["unit_symbol"]); ?>
                    </span>

                    <span class="weather-pill">
                        Humidity: <?php echo htmlspecialchars($weatherData["humidity"]); ?>%
                    </span>

                    <span class="weather-pill">
                        Wind: <?php echo htmlspecialchars($weatherData["wind_speed"]); ?> m/s
                    </span>
                </div>
            </div>

            <div class="weather-main">
                <?php if (!empty($weatherData["icon"])): ?>
                    <img src="<?php echo htmlspecialchars($weatherObj->getIconUrl($weatherData["icon"])); ?>" alt="Weather Icon">
                <?php endif; ?>

                <div class="weather-temp">
                    <h3>
                        <?php echo htmlspecialchars($weatherData["temperature"]); ?><?php echo htmlspecialchars($weatherData["unit_symbol"]); ?>
                    </h3>
                    <span><?php echo htmlspecialchars($weatherData["main"]); ?></span>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="weather-error">
        Weather widget unavailable:
        <?php echo htmlspecialchars($weatherData["message"]); ?>
    </section>
<?php endif; ?>