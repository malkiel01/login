/**
 * PDF Generator - Global Function for mPDF
 * 
 * Usage:
 * generatePDF(jsonData).then(result => console.log(result));
 * 
 * או בתוך async function:
 * const result = await generatePDF(jsonData);
 */

// הגדרת משתנה גלובלי לכתובת השרת
window.PDF_SERVER_URL = window.PDF_SERVER_URL || 'https://login.form.mbe-plus.com/dashboard/dashboards/print/';

/**
 * פונקציה גלובלית ליצירת PDF
 * @param {Object} jsonData - נתוני JSON עם הפרמטרים
 * @returns {Promise} - מחזיר Promise עם תוצאת היצירה
 */
window.generatePDF = async function(jsonData) {
    // ולידציה בסיסית
    if (!jsonData || typeof jsonData !== 'object') {
        throw new Error('Invalid JSON data provided');
    }

    // ברירות מחדל
    const defaultConfig = {
        method: 'mpdf',
        language: 'he',
        orientation: 'P',
        filename: null,
        values: []
    };

    // מיזוג עם ברירות המחדל
    const config = Object.assign({}, defaultConfig, jsonData);

    // ולידציה של השדות החובה
    if (!Array.isArray(config.values) || config.values.length === 0) {
        throw new Error('No values provided in JSON');
    }

    // ולידציה של כל ערך
    config.values = config.values.map((value, index) => {
        if (!value.text) {
            console.warn(`Value at index ${index} missing text, skipping...`);
            return null;
        }
        
        return {
            text: String(value.text),
            x: parseInt(value.x) || 100,
            y: parseInt(value.y) || 100,
            fontSize: parseInt(value.fontSize) || 12,
            color: value.color || '#000000'
        };
    }).filter(v => v !== null);

    if (config.values.length === 0) {
        throw new Error('No valid values found after validation');
    }

    // כתובת ה-API
    const apiUrl = `${window.PDF_SERVER_URL}pdf-mpdf-overlay.php`;

    console.log('🚀 Generating PDF with config:', config);

    try {
        // שליחת הבקשה
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(config)
        });

        // בדיקת תגובת השרת
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // קבלת התוצאה
        const result = await response.json();

        if (result.success) {
            console.log('✅ PDF created successfully:', result);
            
            // הורדה אוטומטית של הקובץ
            if (result.view_url || result.direct_url) {
                const pdfUrl = result.view_url || result.direct_url;
                await downloadPDF(pdfUrl, result.filename);
            }
            
            return {
                success: true,
                message: 'PDF generated and downloaded successfully',
                data: result
            };
        } else {
            throw new Error(result.error || 'PDF generation failed');
        }

    } catch (error) {
        console.error('❌ Error generating PDF:', error);
        return {
            success: false,
            error: error.message,
            details: error
        };
    }
};

/**
 * פונקציית עזר להורדת הקובץ
 * @param {string} url - כתובת הקובץ
 * @param {string} filename - שם הקובץ
 */
async function downloadPDF(url, filename) {
    try {
        // יצירת לינק להורדה
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || `pdf_${Date.now()}.pdf`;
        link.target = '_blank';
        
        // הוספה ל-DOM, לחיצה והסרה
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('📥 Download initiated for:', filename);
    } catch (error) {
        console.error('Error downloading PDF:', error);
        // פתיחה בחלון חדש כ-fallback
        window.open(url, '_blank');
    }
}

/**
 * פונקציית עזר לבדיקת התחברות
 */
window.testPDFConnection = async function() {
    const apiUrl = `${window.PDF_SERVER_URL}pdf-mpdf-overlay.php?test=1`;
    
    try {
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        if (data.success) {
            console.log('✅ Connection successful:', data);
            return true;
        }
    } catch (error) {
        console.error('❌ Connection failed:', error);
        return false;
    }
};

/**
 * דוגמאות שימוש
 */
window.pdfExamples = {
    // דוגמה בסיסית
    basic: {
        values: [
            {
                text: "שלום עולם",
                x: 100,
                y: 100,
                fontSize: 20,
                color: "#000000"
            }
        ]
    },
    
    // דוגמה עם קובץ קיים
    withFile: {
        filename: "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf",
        orientation: "L",
        language: "he",
        values: [
            {
                text: "כותרת ראשית",
                x: 200,
                y: 50,
                fontSize: 24,
                color: "#FF0000"
            },
            {
                text: "טקסט משני",
                x: 200,
                y: 100,
                fontSize: 16,
                color: "#0000FF"
            }
        ]
    },
    
    // דוגמה מורכבת
    complex: {
        filename: "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf",
        orientation: "P",
        language: "he",
        values: [
            {
                text: "חשבונית מס",
                x: 300,
                y: 50,
                fontSize: 28,
                color: "#000080"
            },
            {
                text: "תאריך: " + new Date().toLocaleDateString('he-IL'),
                x: 100,
                y: 150,
                fontSize: 14,
                color: "#333333"
            },
            {
                text: "מספר חשבונית: 2024-001",
                x: 100,
                y: 180,
                fontSize: 14,
                color: "#333333"
            },
            {
                text: "סה״כ לתשלום: ₪1,500",
                x: 100,
                y: 400,
                fontSize: 18,
                color: "#008000"
            }
        ]
    }
};

// הודעה שהספרייה נטענה
console.log('📚 PDF Generator loaded successfully!');
console.log('Usage: generatePDF(jsonData)');
console.log('Examples available in: window.pdfExamples');
console.log('Test connection: testPDFConnection()');

// אפשרות להרצה מיידית עם דוגמה
if (window.location.hash === '#test') {
    console.log('Running test example...');
    generatePDF(window.pdfExamples.basic);
}