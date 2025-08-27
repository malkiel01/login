<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Panel - התראות</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, 'Segoe UI', sans-serif;
            background: #1a1a1a;
            color: #0f0;
            min-height: 100vh;
            padding: 20px;
        }
        
        .debug-container {
            max-width: 800px;
            margin: 0 auto;
            background: #000;
            border: 2px solid #0f0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }
        
        h1 {
            color: #0f0;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px #0f0;
            font-size: 24px;
        }
        
        .status-panel {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #333;
        }
        
        .status-row:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: #888;
        }
        
        .status-value {
            color: #0f0;
            font-weight: bold;
        }
        
        .status-value.denied {
            color: #f00;
        }
        
        .status-value.default {
            color: #ff0;
        }
        
        .notification-form {
            background: #111;
            border: 1px solid #0f0;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            color: #0f0;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            background: #000;
            border: 1px solid #0f0;
            color: #0f0;
            border-radius: 3px;
            font-family: monospace;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            box-shadow: 0 0 5px #0f0;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        button {
            padding: 12px 20px;
            background: #000;
            border: 2px solid #0f0;
            color: #0f0;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        button:hover {
            background: #0f0;
            color: #000;
            box-shadow: 0 0 10px #0f0;
        }
        
        button.danger {
            border-color: #f00;
            color: #f00;
        }
        
        button.danger:hover {
            background: #f00;
            color: #fff;
            box-shadow: 0 0 10px #f00;
        }
        
        .console-output {
            background: #000;
            border: 1px solid #0f0;
            padding: 15px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            border-radius: 5px;
        }
        
        .console-line {
            margin: 2px 0;
            padding: 2px;
        }
        
        .console-time {
            color: #888;
        }
        
        .console-success {
            color: #0f0;
        }
        
        .console-error {
            color: #f00;
        }
        
        .console-info {
            color: #0ff;
        }
        
        .notification-counter {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            border-radius: 5px;
        }
        
        .counter-display {
            font-size: 48px;
            color: #0f0;
            text-shadow: 0 0 10px #0f0;
            font-family: monospace;
        }
        
        .preset-buttons {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .preset-title {
            color: #0f0;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
111
</body>
</html>