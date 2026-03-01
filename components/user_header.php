<?php
namespace App\User\Presentation;
use Exception;
use PDOException;
use PDO;

// Define ROOT_DIR
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
    error_log("ROOT_DIR defined as: " . ROOT_DIR);
}

ob_start();

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start error in user_header.php: " . $e->getMessage());
    }
}

require_once ROOT_DIR . '/components/connect.php';
require_once ROOT_DIR . '/components/currency.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
error_log("user_header.php user_id: " . ($user_id ?: 'empty'));

// Fetch is_premium from database if user_id exists and is_premium is not set
if (!empty($user_id) && !isset($_SESSION['is_premium'])) {
    try {
        $conn = get_db_connection();
        $select_premium = $conn->prepare("SELECT is_premium FROM users WHERE user_id = ?");
        $select_premium->execute([$user_id]);
        $user = $select_premium->fetch(PDO::FETCH_ASSOC);
        $_SESSION['is_premium'] = $user['is_premium'] ?? false;
        error_log("Fetched is_premium from database: " . ($_SESSION['is_premium'] ? 'true' : 'false'));
    } catch (PDOException $e) {
        error_log("Failed to fetch is_premium from database: " . $e->getMessage());
        $_SESSION['is_premium'] = false;
    }
}

// Load language from database for logged-in users only if not already set
if (!empty($user_id) && !isset($_SESSION['language'])) {
    try {
        $conn = get_db_connection();
        $select_language = $conn->prepare("SELECT language FROM users WHERE user_id = ?");
        $select_language->execute([$user_id]);
        $user = $select_language->fetch(PDO::FETCH_ASSOC);
        $_SESSION['language'] = $user['language'] ?? 'en';
        error_log("Loaded language from database for user_id $user_id: " . $_SESSION['language']);
    } catch (PDOException $e) {
        error_log("Failed to load language from database: " . $e->getMessage());
        $_SESSION['language'] = 'en';
    }
} elseif (!isset($_SESSION['language'])) {
    // Default language for non-logged-in users
    $_SESSION['language'] = 'en';
    error_log("Set default language for non-logged-in user: " . $_SESSION['language']);
}

error_log("Current session language: " . $_SESSION['language']);

// Handle currency change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currency'])) {
    $currency = $_POST['currency'];
    $allowed_currencies = ['EUR', 'USD', 'ETB'];
    if (in_array($currency, $allowed_currencies)) {
        $_SESSION['currency'] = $currency;
        error_log("Currency set to: $currency");
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
        exit;
    } else {
        error_log("Invalid currency selected: $currency");
    }
}

// Set default currency if not set
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'EUR';
    error_log("Set default currency: EUR");
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['language']); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <!-- CSS to hide Google Translate branding and ensure visibility -->
    <style>
        .goog-logo-link {
            display: none !important;
        }
        .goog-te-gadget {
            color: transparent !important;
        }
        /* Hide default Google Translate dropdown */
        #google_translate_element select {
            display: none !important;
        }
        /* Ensure custom language selector is visible */
        select[name="language"] {
            display: inline-block !important;
            padding: 5px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
    <!-- Google Translate Script -->
    <script type="text/javascript">
        // Load Google Translate script with fallback
        function loadGoogleTranslateScript(callback) {
            const script = document.createElement('script');
            script.src = '//translate.google.com/translate_a/element.js?cb=' + callback;
            script.async = true;
            script.onerror = () => {
                console.error('Failed to load Google Translate script. Retrying...');
                setTimeout(() => loadGoogleTranslateScript(callback), 2000); // Retry after 2 seconds
            };
            document.head.appendChild(script);
        }

        function googleTranslateElementInit() {
            try {
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    includedLanguages: 'en,it,am',
                    autoDisplay: false
                }, 'google_translate_element');
                console.log('Google Translate initialized');

                // Sync custom dropdown with Google Translate
                function syncGoogleTranslate(lang, retryCount = 0) {
                    const maxRetries = 5;
                    const googCombo = document.querySelector('.goog-te-combo');
                    if (googCombo) {
                        googCombo.value = lang;
                        googCombo.dispatchEvent(new Event('change'));
                        console.log('Google Translate set to:', lang);
                    } else if (retryCount < maxRetries) {
                        console.warn(`Google Translate combo not found, retrying (${retryCount + 1}/${maxRetries})...`);
                        setTimeout(() => syncGoogleTranslate(lang, retryCount + 1), 1000);
                    } else {
                        console.error('Google Translate combo not found after retries. Check script loading.');
                    }
                }

                const languageSelect = document.querySelector('select[name="language"]');
                if (languageSelect) {
                    languageSelect.value = '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
                    syncGoogleTranslate('<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>'); // Initial sync
                    languageSelect.addEventListener('change', function() {
                        const lang = this.value;
                        console.log('Language changed to:', lang);

                        // Set Google Translate language
                        syncGoogleTranslate(lang);

                        // Save language to session and database
                        fetch('/components/save_language.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'language=' + encodeURIComponent(lang)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('Language saved successfully:', lang);
                                window.location.reload(); // Reload to apply changes
                            } else {
                                console.error('Failed to save language:', data.error);
                            }
                        })
                        .catch(error => console.error('Error saving language:', error));
                    });
                } else {
                    console.error('Custom language selector not found. Ensure select[name="language"] exists.');
                }
            } catch (error) {
                console.error('Google Translate initialization failed:', error);
            }
        }

        // Load Google Translate script
        loadGoogleTranslateScript('googleTranslateElementInit');
    </script>
</head>
<body>
<header class="header">
    <nav class="navbar nav-1">
        <section class="flex">
            <a href="/index.php" class="logo"><i class="fas fa-house"></i>MyHome</a>
            <ul>
                <?php if (!empty($user_id)): ?>
                    <li><a href="/app/property/presentation/post_property.php">Post Property<i class="fas fa-paper-plane"></i></a></li>
                    <li><a href="/app/chat/presentation/chat.php">Chat<i class="fas fa-comments"></i></a></li>
                <?php endif; ?>
            </ul>
        </section>
    </nav>

    <nav class="navbar nav-2">
        <section class="flex">
            <div id="menu-btn" class="fas fa-bars"></div>
            <div class="menu">
                <ul>
                    <li><a href="#">Menu<i class="fas fa-angle-down"></i></a>
                        <ul>
                            <li><a href="/app/user/presentation/dashboard.php">Activities</a></li>
                            <li><a href="/app/property/presentation/post_property.php">Post Property</a></li>
                            <li><a href="/app/user/presentation/my_listings.php">My Listings</a></li>
                        </ul>
                    </li>
                    <li><a href="#">Properties<i class="fas fa-angle-down"></i></a>
                        <ul>
                            <li><a href="/app/property/presentation/search.php">Filter Search</a></li>
                            <li><a href="/app/user/presentation/listings.php">All Your Homes</a></li>
                        </ul>
                    </li>
                    <li>
                        <form method="post" action="" style="display:inline;">
                            <select name="currency" onchange="this.form.submit()" style="padding: 5px; border: none; background: transparent; font: inherit; color: inherit; cursor: pointer;">
                                <option value="EUR" <?= ($_SESSION['currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                <option value="USD" <?= ($_SESSION['currency'] ?? 'EUR') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                <option value="ETB" <?= ($_SESSION['currency'] ?? 'EUR') === 'ETB' ? 'selected' : '' ?>>ETB (ብር)</option>
                            </select>
                        </form>
                    </li>
                    <li>
                        <form method="post" action="/components/save_language.php" style="display:inline;">
                            <select name="language" style="padding: 5px; border: none; background: transparent; font: inherit; color: inherit; cursor: pointer;">
                                <option value="en" <?= ($_SESSION['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="it" <?= ($_SESSION['language'] ?? 'en') === 'it' ? 'selected' : '' ?>>Italian</option>
                                <option value="am" <?= ($_SESSION['language'] ?? 'en') === 'am' ? 'selected' : '' ?>>Amharic</option>
                            </select>
                        </form>
                    </li>
                    <li><div id="google_translate_element" style="display: none;"></div></li>
                    <li><a href="#">Help<i class="fas fa-angle-down"></i></a>
                        <ul>
                            <li><a href="/app/user/presentation/about.php">About Us</a></li>
                            <li><a href="/app/user/presentation/contact.php">Contact Us</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <ul>
                <li><a href="/app/user/presentation/saved.php">Saved <i class="far fa-heart"></i></a></li>
                <li>
                    <?php if (isset($_SESSION['is_premium']) && $_SESSION['is_premium']): ?>
                        <a href="/app/subscription/presentation/manage_plan.php" class="premium-btn">Manage Plan</a>
                    <?php else: ?>
                        <a href="/app/subscription/presentation/subscribe.php" class="premium-btn">Upgrade Plan</a>
                    <?php endif; ?>
                </li>
                <li><a href="#">My Account <i class="fas fa-angle-down"></i></a>
                    <ul>
                        <?php if (!empty($user_id)): ?>
                            <li><a href="/app/user/presentation/update.php">Update Profile</a></li>
                            <li><a href="/components/user_logout.php" onclick="return confirm('Logout From this website?');">Logout</a></li>
                        <?php else: ?>
                            <li><a href="/app/auth/presentation/login.php">Login Now</a></li>
                            <li><a href="/app/auth/presentation/register.php">Register New</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </section>
    </nav>
</header>

<?php require_once ROOT_DIR . '/components/chatbot.php'; ?>
<?php ob_end_flush(); ?>
</body>
</html>