<?php
// Cache settings
$cache_time = 3600; // 1 hour cache

// Fixer API key from environment
$fixer_api_key = getenv('FIXER_API_KEY');
if (!$fixer_api_key) {
    error_log('FIXER_API_KEY not set in .env file');
    $fixer_api_key = ''; // Will cause API call to fail with proper error
}

// Fetch rates from Fixer API if cache expired or not set
if (!isset($_SESSION['rates_time']) || time() - $_SESSION['rates_time'] > $cache_time) {
    $apiUrl = "http://data.fixer.io/api/latest?access_key={$fixer_api_key}&symbols=USD,ETB,EUR&format=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode == 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success'] === true && isset($data['rates'])) {
            $_SESSION['rates'] = [
                'EUR' => 1, // Base currency
                'USD' => $data['rates']['USD'] ?? 1.1,
                'ETB' => $data['rates']['ETB'] ?? 60,
            ];
            $_SESSION['rates_time'] = time();
            error_log("Fetched exchange rates: " . json_encode($_SESSION['rates']));
        } else {
            error_log("Fixer API error: " . ($data['error']['info'] ?? 'Unknown error'));
            $_SESSION['rates'] = ['EUR' => 1, 'USD' => 1.1, 'ETB' => 60]; // Fallback rates
        }
    } else {
        error_log("Failed to fetch exchange rates from Fixer API. HTTP Code: $httpCode");
        $_SESSION['rates'] = ['EUR' => 1, 'USD' => 1.1, 'ETB' => 60]; // Fallback rates
    }
}

// Use cached or fallback rates
$rates = $_SESSION['rates'] ?? ['EUR' => 1, 'USD' => 1.1, 'ETB' => 60];

/**
 * Convert price from EUR base to selected currency and format with symbol
 *
 * @param float $amount Amount in EUR
 * @param array $rates Exchange rates
 * @return string Formatted price with currency symbol
 */
function convertCurrencyFromEUR($amount, $rates) {
    $currency = $_SESSION['currency'] ?? 'EUR';
    $rate = $rates[$currency] ?? 1;
    $convertedAmount = $amount * $rate;

    $symbols = [
        'USD' => '$',
        'ETB' => 'ብር ',
        'EUR' => '€',
    ];
    $symbol = $symbols[$currency] ?? '€';
    return $symbol . number_format($convertedAmount, 2);
}
?>