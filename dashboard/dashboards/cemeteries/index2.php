<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>וריאציות לכפתור חזרה</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }

        .option-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }

        .option-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2d3748;
        }

        .button-demo {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        /* אופציה 5: כפתור עם breadcrumb */
        .breadcrumb-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: #f7fafc;
            border-radius: 20px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .breadcrumb-home:hover {
            background: white;
            border-color: #cbd5e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .separator {
            color: #cbd5e0;
            margin: 0 5px;
        }

        /* אייקונים */
        .icon {
            width: 20px;
            height: 20px;
        }

        .code-example {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 15px;
            border-left: 3px solid #667eea;
            overflow-x: auto;
        }
    </style>
</head>
<body>


    <!-- אופציה 5: Breadcrumb סטייל -->
    <div class="option-container">
        <h3 class="option-title">אופציה 5: סגנון Breadcrumb</h3>
        <div class="button-demo">
            <a href="/dashboard/" class="breadcrumb-home">
                <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/>
                </svg>
                ראשי
                <span class="separator">›</span>
                בתי עלמין
            </a>
        </div>
        <div class="code-example">
&lt;a href="/dashboard/" class="breadcrumb-home"&gt;<br>
&nbsp;&nbsp;&lt;svg&gt;...&lt;/svg&gt;<br>
&nbsp;&nbsp;ראשי<br>
&nbsp;&nbsp;&lt;span class="separator"&gt;›&lt;/span&gt;<br>
&nbsp;&nbsp;בתי עלמין<br>
&lt;/a&gt;
        </div>
    </div>
</body>
</html>