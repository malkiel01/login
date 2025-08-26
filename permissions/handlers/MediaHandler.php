<?php
/**
 * Media Handler
 * permissions/handlers/MediaHandler.php
 * 
 * טיפול בהרשאות מצלמה ומיקרופון
 */

namespace Permissions\Handlers;

class MediaHandler {
    
    private $storage;
    private $userId;
    
    /**
     * Constructor
     */
    public function __construct($userId = null) {
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->storage = new \Permissions\Core\PermissionStorage();
    }
    
    /**
     * בדיקת הרשאת מצלמה
     */
    public function checkCameraPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'camera');
    }
    
    /**
     * בדיקת הרשאת מיקרופון
     */
    public function checkMicrophonePermission() {
        return $this->storage->getPermissionStatus($this->userId, 'microphone');
    }
    
    /**
     * קבלת הגדרות מדיה
     */
    public function getMediaSettings() {
        return [
            'camera' => [
                'enabled' => true,
                'resolution' => 'hd',
                'facing_mode' => 'user'
            ],
            'microphone' => [
                'enabled' => true,
                'echo_cancellation' => true,
                'noise_suppression' => true,
                'auto_gain_control' => true
            ]
        ];
    }
    
    /**
     * קבלת רשימת מכשירי מדיה
     */
    public function getDevicesList() {
        // זה נעשה בצד הלקוח בלבד
        return [
            'message' => 'Device enumeration must be done client-side',
            'javascript' => 'navigator.mediaDevices.enumerateDevices()'
        ];
    }
    
    /**
     * הגדרות איכות למדיה
     */
    public function getQualityConstraints($type = 'camera') {
        $constraints = [
            'camera' => [
                'low' => [
                    'video' => [
                        'width' => ['ideal' => 640],
                        'height' => ['ideal' => 480]
                    ]
                ],
                'medium' => [
                    'video' => [
                        'width' => ['ideal' => 1280],
                        'height' => ['ideal' => 720]
                    ]
                ],
                'high' => [
                    'video' => [
                        'width' => ['ideal' => 1920],
                        'height' => ['ideal' => 1080]
                    ]
                ],
                'ultra' => [
                    'video' => [
                        'width' => ['min' => 2560],
                        'height' => ['min' => 1440]
                    ]
                ]
            ],
            'microphone' => [
                'low' => [
                    'audio' => [
                        'sampleRate' => 8000,
                        'echoCancellation' => false
                    ]
                ],
                'medium' => [
                    'audio' => [
                        'sampleRate' => 16000,
                        'echoCancellation' => true,
                        'noiseSuppression' => true
                    ]
                ],
                'high' => [
                    'audio' => [
                        'sampleRate' => 48000,
                        'echoCancellation' => true,
                        'noiseSuppression' => true,
                        'autoGainControl' => true
                    ]
                ]
            ]
        ];
        
        return $constraints[$type] ?? $constraints;
    }
    
    /**
     * רישום שימוש במדיה
     */
    public function logMediaUsage($type, $duration = null, $metadata = []) {
        $data = [
            'user_id' => $this->userId,
            'media_type' => $type,
            'duration' => $duration,
            'metadata' => json_encode($metadata),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // כאן ניתן לשמור ב-DB
        return true;
    }
    
    /**
     * בדיקת תמיכה במדיה
     */
    public function checkMediaSupport() {
        return [
            'getUserMedia' => true,
            'mediaDevices' => true,
            'mediaRecorder' => true,
            'screen_capture' => true,
            'picture_in_picture' => true
        ];
    }
}