<!-- Export Modal (Dynamically created by JavaScript) -->
<!-- This file contains the structure that will be created by exportToJson() function -->
<script>
// Modal template for export functionality
const exportModalTemplate = `
    <div id="exportModal" style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        z-index: 10000;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        direction: rtl;
    ">
        <h3 style="margin-bottom: 15px; color: #333;">📤 JSON מוכן לייצוא</h3>
        <p style="margin-bottom: 10px; color: #666;">העתק את ה-JSON הבא לשימוש בפונקציה הגלובלית:</p>
        <textarea id="exportedJson" style="
            width: 100%;
            height: 300px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 10px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            background: #f7f9fc;
            resize: vertical;
            direction: ltr;
            text-align: left;
        " readonly></textarea>
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
            <button onclick="copyJsonToClipboard()" style="
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                background: #667eea;
                color: white;
                cursor: pointer;
                font-weight: 600;
            ">📋 העתק ללוח</button>
            <button onclick="downloadJson()" style="
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                background: #48bb78;
                color: white;
                cursor: pointer;
                font-weight: 600;
            ">💾 הורד כקובץ</button>
            <button onclick="closeExportModal()" style="
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                background: #f56565;
                color: white;
                cursor: pointer;
                font-weight: 600;
            ">✖️ סגור</button>
        </div>
    </div>
`;

const exportBackdropTemplate = `
    <div id="modalBackdrop" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
    " onclick="closeExportModal()"></div>
`;
</script>