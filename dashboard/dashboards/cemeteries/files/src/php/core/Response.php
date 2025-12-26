<?php
/**
 * Response Helper Class
 *
 * מחלקת עזר לטיפול בתשובות JSON בצורה אחידה ונקייה.
 * משמשת בכל ה-API endpoints להחזרת תשובות למשתמש.
 *
 * @package PDFEditor\Core
 * @version 1.0.0
 * @since Phase 2 Refactoring
 */

namespace PDFEditor\Core;

class Response {

    /**
     * תשובת הצלחה
     *
     * שולחת תשובת JSON עם success: true ונתונים נוספים
     *
     * @param array $data נתונים להחזרה
     * @param string $message הודעה אופציונלית
     * @param int $statusCode HTTP status code (default: 200)
     * @return void (exits after output)
     *
     * @example
     * Response::success([
     *     'output_file' => 'processed.pdf',
     *     'pages' => 5
     * ], 'הקובץ עובד בהצלחה');
     */
    public static function success($data = [], $message = '', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = ['success' => true];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        // Merge data into response
        $response = array_merge($response, $data);

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * תשובת שגיאה
     *
     * שולחת תשובת JSON עם success: false והודעת שגיאה
     *
     * @param string $error הודעת שגיאה
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $details פרטים נוספים אופציונליים
     * @return void (exits after output)
     *
     * @example
     * Response::error('הקובץ חייב להיות PDF', 400);
     */
    public static function error($error, $statusCode = 400, $details = []) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => false,
            'error' => $error
        ];

        // Add optional details
        if (!empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * תשובת JSON כללית
     *
     * שולחת תשובת JSON מותאמת אישית
     *
     * @param array $data נתוני התשובה
     * @param int $statusCode HTTP status code (default: 200)
     * @return void (exits after output)
     *
     * @example
     * Response::json(['custom' => 'data']);
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * תשובת שגיאה עם exception
     *
     * שולחת תשובת שגיאה מבוססת על exception
     * בדרך כלל משמשת ב-catch blocks
     *
     * @param \Exception $e אובייקט Exception
     * @param int $statusCode HTTP status code (default: 500)
     * @param bool $includeTrace האם לכלול stack trace (רק ב-debug mode)
     * @return void (exits after output)
     *
     * @example
     * try {
     *     // code...
     * } catch (Exception $e) {
     *     Response::exception($e);
     * }
     */
    public static function exception($e, $statusCode = 500, $includeTrace = false) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => false,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];

        // Include stack trace only in debug mode
        if ($includeTrace && defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['trace'] = $e->getTraceAsString();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * תשובת "לא נמצא"
     *
     * תשובה מהירה למשאב שלא נמצא
     *
     * @param string $resource שם המשאב שלא נמצא
     * @return void (exits after output)
     *
     * @example
     * Response::notFound('קובץ');
     */
    public static function notFound($resource = 'המשאב') {
        self::error("{$resource} לא נמצא", 404);
    }

    /**
     * תשובת "לא מורשה"
     *
     * תשובה מהירה לגישה לא מורשית
     *
     * @param string $message הודעה מותאמת אישית
     * @return void (exits after output)
     *
     * @example
     * Response::unauthorized();
     */
    public static function unauthorized($message = 'גישה לא מורשית') {
        self::error($message, 401);
    }

    /**
     * תשובת "נתונים חסרים"
     *
     * תשובה מהירה לבקשה עם נתונים חסרים
     *
     * @param array $missingFields שדות חסרים
     * @return void (exits after output)
     *
     * @example
     * Response::missingData(['pdf', 'texts']);
     */
    public static function missingData($missingFields = []) {
        $message = 'נתונים חסרים';

        if (!empty($missingFields)) {
            $message .= ': ' . implode(', ', $missingFields);
        }

        self::error($message, 400, ['missing_fields' => $missingFields]);
    }

    /**
     * תשובת "ולידציה נכשלה"
     *
     * תשובה מהירה לבעיות ולידציה
     *
     * @param array $errors מערך שגיאות ולידציה
     * @return void (exits after output)
     *
     * @example
     * Response::validationFailed([
     *     'name' => 'שם קצר מדי',
     *     'email' => 'אימייל לא תקין'
     * ]);
     */
    public static function validationFailed($errors) {
        self::error('הולידציה נכשלה', 422, ['validation_errors' => $errors]);
    }
}
