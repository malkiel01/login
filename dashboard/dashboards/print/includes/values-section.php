<div class="section">
    <h2 class="section-title">➕ הוספת ערכים</h2>
    
    <div class="form-group">
        <label for="textValue">טקסט להדפסה:</label>
        <input type="text" id="textValue" placeholder="הכנס טקסט...">
    </div>

    <div class="form-group">
        <label>קואורדינטות (X, Y):</label>
        <div class="coordinates-inputs">
            <input type="number" id="xCoord" placeholder="X" min="0" value="<?= $config['defaults']['x'] ?>">
            <input type="number" id="yCoord" placeholder="Y" min="0" value="<?= $config['defaults']['y'] ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="fontSize">גודל גופן:</label>
        <input type="number" 
               id="fontSize" 
               placeholder="12" 
               min="<?= $config['limits']['min_font_size'] ?>" 
               max="<?= $config['limits']['max_font_size'] ?>" 
               value="<?= $config['defaults']['fontSize'] ?>">
    </div>

    <div class="form-group">
        <label for="fontColor">צבע טקסט:</label>
        <div class="color-input-wrapper">
            <input type="color" id="fontColor" value="<?= $config['defaults']['color'] ?>" onchange="updateColorPreview()">
            <div class="color-preview" id="colorPreview"><?= $config['defaults']['color'] ?></div>
        </div>
    </div>

    <button class="btn btn-secondary" onclick="addValue()">
        ➕ הוסף ערך
    </button>

    <div class="preview-area" id="previewArea">
        <span>אין ערכים להצגה</span>
    </div>
</div>