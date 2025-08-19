<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // נדרש להתקין את Google API Client Library

// הגדרות Google OAuth
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'https://your-domain.com/google-auth.php');

// יצירת Google Client
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');

// אם אין קוד אימות, הפנה למסך ההתחברות של גוגל
if (!isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit;
}

// קיבלנו קוד אימות מגוגל
try {
    // החלפת הקוד בטוקן
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);
    
    // קבלת פרטי המשתמש מגוגל
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $google_id = $google_account_info->id;
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $picture = $google_account_info->picture;
    
    // חיבור למסד הנתונים
    $pdo = getDBConnection();
    
    // בדיקה אם המשתמש קיים
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // משתמש קיים - עדכון פרטים
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET google_id = ?, 
                name = ?, 
                profile_picture = ?,
                last_login = NOW(),
                auth_type = 'google'
            WHERE id = ?
        ");
        $updateStmt->execute([$google_id, $name, $picture, $user['id']]);
        
        $user_id = $user['id'];
        $username = $user['username'];
    } else {
        // משתמש חדש - יצירת רשומה
        $username = explode('@', $email)[0] . '_' . substr($google_id, 0, 4);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, email, google_id, name, profile_picture, auth_type, last_login) 
            VALUES (?, ?, ?, ?, ?, 'google', NOW())
        ");
        $insertStmt->execute([$username, $email, $google_id, $name, $picture]);
        
        $user_id = $pdo->lastInsertId();
    }
    
    // שמירת פרטי המשתמש בסשן
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['profile_picture'] = $picture;
    $_SESSION['auth_type'] = 'google';
    
    // הפניה לדף הראשי
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // במקרה של שגיאה, חזרה לדף ההתחברות
    $_SESSION['error'] = 'שגיאה בהתחברות עם Google: ' . $e->getMessage();
    header('Location: login.php');
    exit;
}