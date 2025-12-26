<?php
/**
 * PDF Service
 *
 * שירות מרכזי לעיבוד קבצי PDF - הוספת טקסטים ותמונות
 *
 * @package PDFEditor\Services
 * @version 1.0.0
 * @since Phase 2 Refactoring
 */

namespace PDFEditor\Services;

use PDFEditor\Config;

class PDFService {

    private $uploadDir;
    private $outputDir;
    private $pythonPath;
    private $pythonScript;

    /**
     * Constructor
     *
     * @param string|null $uploadDir תיקיית העלאות (אופציונלי)
     * @param string|null $outputDir תיקיית פלטים (אופציונלי)
     */
    public function __construct($uploadDir = null, $outputDir = null) {
        $this->uploadDir = $uploadDir ?? Config::UPLOAD_DIR;
        $this->outputDir = $outputDir ?? Config::OUTPUT_DIR;
        $this->pythonPath = Config::PYTHON_VENV;
        $this->pythonScript = Config::PYTHON_SCRIPT_LEGACY; // Using legacy for now

        // Ensure directories exist
        $this->ensureDirectories();
    }

    /**
     * וידוא קיום תיקיות
     */
    private function ensureDirectories() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * עיבוד קובץ PDF
     *
     * מעבד קובץ PDF עם הוספת טקסטים ותמונות
     *
     * @param array $file $_FILES['pdf']
     * @param array $items מערך פריטים (טקסטים + תמונות)
     * @return array ['success' => bool, 'output_file' => string, 'pages' => int, 'width' => float, 'height' => float, 'error' => string|null]
     *
     * @throws \Exception
     *
     * @example
     * $pdfService = new PDFService();
     * $result = $pdfService->process($_FILES['pdf'], $allItems);
     */
    public function process($file, $items) {
        // Validate PDF file
        $validation = ValidationService::validatePDFFile($file);
        if (!$validation['valid']) {
            throw new \Exception($validation['error']);
        }

        // Validate items (optional - can be empty)
        if (!empty($items)) {
            $itemsValidation = ValidationService::validateItems($items);
            if (!$itemsValidation['valid']) {
                throw new \Exception($itemsValidation['error']);
            }
        }

        // Generate unique ID for this processing
        $uniqueId = FileService::generateUniqueId('pdf_');

        // Define file paths
        $inputFilename = $uniqueId . '_input.pdf';
        $outputFilename = $uniqueId . '_output.pdf';
        $dataFilename = $uniqueId . '_data.json';

        $inputPath = $this->uploadDir . $inputFilename;
        $outputPath = $this->outputDir . $outputFilename;
        $dataPath = $this->uploadDir . $dataFilename;

        try {
            // Save uploaded file
            $saveResult = FileService::saveUploadedFile($file, $this->uploadDir, $inputFilename);
            if (!$saveResult['success']) {
                throw new \Exception($saveResult['error']);
            }

            // Prepare data for Python script
            $data = [
                'allItems' => $items,
                'texts' => array_filter($items, fn($item) => ($item['type'] ?? '') === 'text'),
                'images' => array_filter($items, fn($item) => ($item['type'] ?? '') === 'image')
            ];

            // Save data file
            $jsonResult = FileService::writeJsonFile($dataPath, $data);
            if (!$jsonResult['success']) {
                throw new \Exception($jsonResult['error']);
            }

            // Call Python processor
            $result = $this->callPythonProcessor($inputPath, $outputPath, $dataPath);

            // Cleanup temporary files
            @unlink($dataPath);
            @unlink($inputPath);

            if (!$result['success']) {
                @unlink($outputPath);
                throw new \Exception($result['error'] ?? 'שגיאה בעיבוד הקובץ');
            }

            return [
                'success' => true,
                'output_file' => $outputFilename,
                'pages' => $result['pages'] ?? 0,
                'width' => $result['width'] ?? 0,
                'height' => $result['height'] ?? 0
            ];

        } catch (\Exception $e) {
            // Cleanup on error
            @unlink($inputPath);
            @unlink($outputPath);
            @unlink($dataPath);

            throw $e;
        }
    }

    /**
     * קריאה לסקריפט Python
     *
     * @param string $inputPath נתיב קובץ קלט
     * @param string $outputPath נתיב קובץ פלט
     * @param string $dataPath נתיב קובץ נתונים
     * @return array תוצאת העיבוד
     *
     * @throws \Exception
     */
    private function callPythonProcessor($inputPath, $outputPath, $dataPath) {
        // Build command
        $command = sprintf(
            '%s %s %s %s %s 2>&1',
            escapeshellarg($this->pythonPath),
            escapeshellarg($this->pythonScript),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
            escapeshellarg($dataPath)
        );

        // Execute command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Parse output
        $result = $this->parseOutput($output);

        // Check if execution succeeded
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => 'שגיאה בהרצת Python: ' . implode("\n", $output)
            ];
        }

        // Check if result is valid
        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'error' => 'פלט Python לא תקין: ' . implode("\n", $output)
            ];
        }

        return $result;
    }

    /**
     * ניתוח פלט Python
     *
     * מסנן שורות DEBUG ומנתח JSON
     *
     * @param array $output שורות פלט
     * @return array|null תוצאה מנותחת
     */
    private function parseOutput($output) {
        // Filter out DEBUG lines
        $jsonLines = [];
        foreach ($output as $line) {
            if (strpos($line, 'DEBUG:') === false && strpos($line, 'Warning:') === false) {
                $jsonLines[] = $line;
            }
        }

        $jsonOutput = implode("\n", $jsonLines);

        // Try to decode JSON
        $result = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing failed, return raw output for debugging
            return [
                'success' => false,
                'error' => 'שגיאה בניתוח פלט: ' . json_last_error_msg(),
                'raw_output' => $jsonOutput
            ];
        }

        return $result;
    }

    /**
     * ניקוי קבצים זמניים ישנים
     *
     * @param int $maxAge גיל מקסימלי בשניות (default: 3600 = 1 hour)
     * @return array תוצאת הניקוי
     */
    public function cleanOldFiles($maxAge = 3600) {
        $uploadResult = FileService::cleanOldFiles($this->uploadDir, $maxAge);
        $outputResult = FileService::cleanOldFiles($this->outputDir, $maxAge);

        return [
            'success' => true,
            'uploads_deleted' => $uploadResult['deleted_count'],
            'outputs_deleted' => $outputResult['deleted_count'],
            'total_deleted' => $uploadResult['deleted_count'] + $outputResult['deleted_count']
        ];
    }

    /**
     * קבלת מטא-דאטה של PDF מעובד
     *
     * @param string $outputFilename שם קובץ פלט
     * @return array|null מטא-דאטה
     */
    public function getProcessedFileMetadata($outputFilename) {
        $filepath = $this->outputDir . $outputFilename;
        return FileService::getFileMetadata($filepath);
    }

    /**
     * מחיקת קובץ מעובד
     *
     * @param string $outputFilename שם קובץ פלט
     * @return array תוצאת המחיקה
     */
    public function deleteProcessedFile($outputFilename) {
        // Validate filename
        $validation = ValidationService::validateOutputFilename($outputFilename);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        $filepath = $this->outputDir . $outputFilename;

        // Validate path
        $pathValidation = ValidationService::validateFilePath($filepath, $this->outputDir);
        if (!$pathValidation['valid']) {
            return [
                'success' => false,
                'error' => $pathValidation['error']
            ];
        }

        return FileService::deleteFile($filepath);
    }

    /**
     * בדיקה אם קובץ מעובד קיים
     *
     * @param string $outputFilename שם קובץ פלט
     * @return bool
     */
    public function processedFileExists($outputFilename) {
        $filepath = $this->outputDir . $outputFilename;
        return FileService::exists($filepath);
    }

    /**
     * קבלת נתיב מלא לקובץ מעובד
     *
     * @param string $outputFilename שם קובץ פלט
     * @return string נתיב מלא
     */
    public function getProcessedFilePath($outputFilename) {
        return $this->outputDir . $outputFilename;
    }
}
