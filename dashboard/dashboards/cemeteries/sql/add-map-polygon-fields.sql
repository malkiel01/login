-- =====================================================
-- Cemetery Map - Database Migration
-- הוספת שדות פוליגון למפת בית העלמין
-- =====================================================
-- תאריך: 2025-12-30
-- גרסה: 1.0
-- =====================================================

-- =====================================================
-- 1. הוספת שדה mapPolygon לטבלת cemeteries
-- =====================================================
ALTER TABLE `cemeteries`
ADD COLUMN `mapPolygon` JSON DEFAULT NULL
COMMENT 'נתוני פוליגון של בית העלמין במפה - מערך נקודות וסגנון';

-- =====================================================
-- 2. הוספת שדה mapBackgroundImage לטבלת cemeteries
-- =====================================================
ALTER TABLE `cemeteries`
ADD COLUMN `mapBackgroundImage` JSON DEFAULT NULL
COMMENT 'תמונת רקע למפה - נתיב, מידות ומיקום';

-- =====================================================
-- 3. הוספת שדה mapSettings לטבלת cemeteries
-- =====================================================
ALTER TABLE `cemeteries`
ADD COLUMN `mapSettings` JSON DEFAULT NULL
COMMENT 'הגדרות מפה - גודל קנבס, זום התחלתי וכו';

-- =====================================================
-- 4. הוספת שדה mapPolygon לטבלת blocks
-- =====================================================
ALTER TABLE `blocks`
ADD COLUMN `mapPolygon` JSON DEFAULT NULL
COMMENT 'נתוני פוליגון של הגוש במפה';

-- =====================================================
-- 5. הוספת שדה mapPolygon לטבלת plots
-- =====================================================
ALTER TABLE `plots`
ADD COLUMN `mapPolygon` JSON DEFAULT NULL
COMMENT 'נתוני פוליגון של החלקה במפה';

-- =====================================================
-- 6. הוספת שדה mapPolygon לטבלת areaGraves
-- =====================================================
ALTER TABLE `areaGraves`
ADD COLUMN `mapPolygon` JSON DEFAULT NULL
COMMENT 'נתוני פוליגון של אחוזת הקבר במפה';

-- =====================================================
-- אימות השינויים
-- =====================================================
-- בדיקה שהשדות נוספו בהצלחה:

SELECT 'cemeteries' as table_name, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cemeteries'
AND COLUMN_NAME IN ('mapPolygon', 'mapBackgroundImage', 'mapSettings')

UNION ALL

SELECT 'blocks' as table_name, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'blocks'
AND COLUMN_NAME = 'mapPolygon'

UNION ALL

SELECT 'plots' as table_name, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'plots'
AND COLUMN_NAME = 'mapPolygon'

UNION ALL

SELECT 'areaGraves' as table_name, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'areaGraves'
AND COLUMN_NAME = 'mapPolygon';

-- =====================================================
-- מבנה ה-JSON הצפוי לכל שדה:
-- =====================================================

/*
mapPolygon:
{
  "points": [
    {"x": 100, "y": 50},
    {"x": 250, "y": 50},
    {"x": 250, "y": 180},
    {"x": 100, "y": 180}
  ],
  "style": {
    "fillColor": "#4CAF50",
    "fillOpacity": 0.3,
    "strokeColor": "#2E7D32",
    "strokeWidth": 2
  },
  "labelPosition": {"x": 175, "y": 115}
}

mapBackgroundImage:
{
  "path": "/uploads/maps/cemetery_1_bg.jpg",
  "width": 2000,
  "height": 1500,
  "offsetX": 0,
  "offsetY": 0,
  "scale": 1
}

mapSettings:
{
  "canvasWidth": 2000,
  "canvasHeight": 1500,
  "initialZoom": 1,
  "minZoom": 0.1,
  "maxZoom": 5,
  "gridEnabled": false,
  "gridSize": 50
}
*/

-- =====================================================
-- סוף הסקריפט
-- =====================================================
