<div class="section">
    <h2 class="section-title">⚙️ הגדרות בסיסיות</h2>
    
    <div class="form-group">
        <label for="pdfUrl">כתובת קובץ PDF (אופציונלי):</label>
        <input type="text" 
               id="pdfUrl" 
               placeholder="https://example.com/file.pdf או השאר ריק ליצירת PDF חדש" 
               dir="ltr"
               value="<?= $config['default_template'] ?>">
        <small>* השאר ריק ליצירת PDF חדש, או הכנס URL לעיבוד PDF קיים</small>
    </div>

    <div class="form-group">
        <label>כיוון הדף:</label>
        <div class="orientation-selector">
            <div class="orientation-btn <?= $config['default_orientation'] === 'P' ? 'active' : '' ?>" 
                 data-orientation="P" 
                 onclick="selectOrientation('P')">
                <i>📄</i>
                <span>אנכי (Portrait)</span>
            </div>
            <div class="orientation-btn <?= $config['default_orientation'] === 'L' ? 'active' : '' ?>" 
                 data-orientation="L" 
                 onclick="selectOrientation('L')">
                <i>📃</i>
                <span>רוחבי (Landscape)</span>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label for="method">שיטת יצירת PDF:</label>
        <div class="method-selector">
            <?php foreach ($config['methods'] as $key => $method): ?>
                <span class="method-badge <?= isset($method['default']) && $method['default'] ? 'active' : '' ?>" 
                      data-method="<?= $key ?>" 
                      onclick="selectMethod('<?= $key ?>')">
                    <?= $method['name'] ?>
                </span>
            <?php endforeach; ?>
        </div>
        <small id="methodDescription">
            <?php 
                $defaultMethod = array_filter($config['methods'], function($m) { return isset($m['default']) && $m['default']; });
                echo reset($defaultMethod)['description'] ?? '';
            ?>
        </small>
    </div>

    <div class="form-group">
        <label for="language">שפה:</label>
        <select id="language">
            <option value="he" <?= $config['default_language'] === 'he' ? 'selected' : '' ?>>עברית (RTL)</option>
            <option value="en" <?= $config['default_language'] === 'en' ? 'selected' : '' ?>>English (LTR)</option>
        </select>
    </div>
</div>