<?php
// הצג שגיאות לדיבוג
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// ============================================
// הגדרות Google OAuth
// ============================================
$CLIENT_ID = '420537994881-gqiev5lqkp6gjj51l1arkjd5q09m5vv0.apps.googleusercontent.com';
$CLIENT_SECRET = 'GOCSPX-YOUR_SECRET_HERE'; // החלף בסוד האמיתי!
$REDIRECT_URI = 'https://form.mbe-plus.com/family/auth/google-auth.php';

// ============================================
// אם אין קוד, הפנה ל-Google
// ============================================
if (!isset($_GET['code'])) {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $CLIENT_ID,
        'redirect_uri' => $REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    
    header('Location: ' . $auth_url);
    exit;
}

// ============================================
// יש קוד - עבד אותו
// ============================================
$code = $_GET['code'];

// שלב 1: החלף קוד בטוקן
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => $CLIENT_ID,
    'client_secret' => $CLIENT_SECRET,
    'redirect_uri' => $REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

// שלח בקשה ל-Google
$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// בדוק אם קיבלנו תשובה תקינה
if ($http_code !== 200) {
    die("שגיאה בקבלת טוקן. קוד: $http_code<br>תשובה: $token_response");
}

$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    die("לא התקבל טוקן. תשובה: " . print_r($token_info, true));
}

// שלב 2: קבל פרטי משתמש
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';

$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_info['access_token']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$user_response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_response, true);

if (!isset($user_info['email'])) {
    die("לא התקבלו פרטי משתמש. תשובה: " . print_r($user_info, true));
}

// שלב 3: טפל במשתמש במסד נתונים
try {
    // טען את קובץ ההגדרות
    require_once '../config.php';
    $pdo = getDBConnection();
    
    // בדוק אם המשתמש קיים
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$user_info['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // משתמש קיים
        $user_id = $user['id'];
        $username = $user['username'];
        $name = $user_info['name'] ?? $user['name'];
        
        // עדכן פרטים
        $update = $pdo->prepare("UPDATE users SET google_id = ?, profile_picture = ?, last_login = NOW() WHERE id = ?");
        $update->execute([$user_info['id'], $user_info['picture'] ?? null, $user_id]);
    } else {
        // משתמש חדש
        $username = explode('@', $user_info['email'])[0] . '_' . rand(1000, 9999);
        $name = $user_info['name'] ?? $user_info['email'];
        
        $insert = $pdo->prepare("
            INSERT INTO users (username, email, google_id, name, profile_picture, auth_type, is_active) 
            VALUES (?, ?, ?, ?, ?, 'google', 1)
        ");
        $insert->execute([
            $username,
            $user_info['email'],
            $user_info['id'],
            $name,
            $user_info['picture'] ?? null
        ]);
        
        $user_id = $pdo->lastInsertId();
    }
    
    // שלב 4: שמור בסשן
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $user_info['email'];
    $_SESSION['profile_picture'] = $user_info['picture'] ?? null;
    
    // שלב 5: הפנה לדף הראשי
    header('Location: ../index2.php');
    exit;
    
} catch (Exception $e) {
    die("שגיאה במסד נתונים: " . $e->getMessage());
}
?>