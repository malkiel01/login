#!/usr/bin/env bash
# קובץ: $HOME/public_html/form/login/scripts/deploy_customers_v2.1.0/deploy.sh
# מטרה: פריסת תיקון למודול ניהול לקוחות (UniversalSearch)
# תאריך: 2025-10-24
# גרסה: v2.1.0 (מתוקן - נתיבים נכונים)
# 
# שלבי פעולה:
# 1. בדיקת קיום נתיבים
# 2. יצירת גיבויים אוטומטיים
# 3. פריסת קבצים מעודכנים מ-payload
# 4. רישום לוג מפורט
# 
# הוראות שחזור:
# להרצת rollback: bash rollback.sh

set -euo pipefail

# ========================================
# הגדרות בסיסיות - ⭐ עם $HOME
# ========================================
PROJECT_ROOT="$HOME/public_html/form/login"
DEPLOY_DIR="$PROJECT_ROOT/scripts/deploy_customers_v2.1.0"
PAYLOAD_DIR="$DEPLOY_DIR/payload"
BACKUPS_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$DEPLOY_DIR/deployment.log"
VERSION="v2.1.0"
DATE=$(date '+%Y-%m-%d')

# ========================================
# צור את קובץ הלוג אם לא קיים
# ========================================
mkdir -p "$DEPLOY_DIR" 2>/dev/null || true
touch "$LOG_FILE" 2>/dev/null || true

# ========================================
# פונקציות עזר
# ========================================
log() {
    local TIMESTAMP=$(date '+%F %T')
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

error_exit() {
    log "❌ שגיאה: $1"
    exit 1
}

# ========================================
# התחלת פריסה
# ========================================
log "🚀 ========================================="
log "🚀 התחלת פריסה $VERSION"
log "🚀 ========================================="
log "📂 נתיב פרויקט: $PROJECT_ROOT"

# ========================================
# בדיקות ראשוניות
# ========================================
log "🔍 בודק תנאים מוקדמים..."

[ -d "$PROJECT_ROOT" ] || error_exit "נתיב פרויקט לא קיים: $PROJECT_ROOT"
[ -d "$PAYLOAD_DIR" ] || error_exit "נתיב payload לא קיים: $PAYLOAD_DIR"

# צור תיקיית גיבויים אם לא קיימת
mkdir -p "$BACKUPS_DIR"
log "✓ תיקיית גיבויים מוכנה: $BACKUPS_DIR"

# ========================================
# פריסת קבצים
# ========================================
log "📦 מתחיל פריסת קבצים..."

FILES_PROCESSED=0
FILES_BACKED_UP=0

cd "$PAYLOAD_DIR" || error_exit "לא ניתן לעבור לתיקיית payload"

while IFS= read -r -d '' FILE; do
    # חלץ נתיב יחסי
    REL_PATH="${FILE#./}"
    TARGET="$PROJECT_ROOT/$REL_PATH"
    
    # פרטי קובץ
    BASENAME="$(basename "$REL_PATH")"
    EXT="${BASENAME##*.}"
    NAME="${BASENAME%.*}"
    
    log "📄 מעבד: $REL_PATH"
    
    # גיבוי אם הקובץ קיים
    if [ -f "$TARGET" ]; then
        BK="$BACKUPS_DIR/${NAME}_backup_${DATE}_${VERSION}.${EXT}"
        
        # אם הגיבוי כבר קיים, הוסף סיומת
        if [ -f "$BK" ]; then
            COUNTER=2
            while [ -f "$BACKUPS_DIR/${NAME}_backup_${DATE}_${VERSION}_${COUNTER}.${EXT}" ]; do
                COUNTER=$((COUNTER + 1))
            done
            BK="$BACKUPS_DIR/${NAME}_backup_${DATE}_${VERSION}_${COUNTER}.${EXT}"
        fi
        
        cp -f "$TARGET" "$BK" || error_exit "כשל בגיבוי: $TARGET"
        log "  💾 גיבוי נוצר: $(basename "$BK")"
        FILES_BACKED_UP=$((FILES_BACKED_UP + 1))
    else
        log "  ⚠️ קובץ חדש (אין גיבוי נדרש)"
        
        # וודא שתיקיית היעד קיימת
        TARGET_DIR="$(dirname "$TARGET")"
        mkdir -p "$TARGET_DIR"
    fi
    
    # העתק קובץ חדש
    cp -f "$FILE" "$TARGET" || error_exit "כשל בהעתקה: $FILE -> $TARGET"
    log "  ✅ הועתק בהצלחה ל: $TARGET"
    
    FILES_PROCESSED=$((FILES_PROCESSED + 1))
    
done < <(find . -type f -print0)

# ========================================
# סיכום
# ========================================
log "🎉 ========================================="
log "🎉 פריסה הסתיימה בהצלחה!"
log "🎉 ========================================="
log "📊 סטטיסטיקות:"
log "  • קבצים שעובדו: $FILES_PROCESSED"
log "  • קבצים שגובו: $FILES_BACKED_UP"
log "  • גרסה: $VERSION"
log "  • תאריך: $DATE"
log ""
log "📝 הערות:"
log "  • כל הגיבויים נשמרו ב: $BACKUPS_DIR"
log "  • לשחזור, הרץ: bash $DEPLOY_DIR/rollback.sh"
log "  • לבדיקה, גש ל: https://login.form.mbe-plus.com/dashboard/dashboards/cemeteries/"
log ""
log "⚠️  חשוב: רענן את הדפדפן עם Ctrl+F5 (hard refresh)"
log ""
log "✅ המערכת מוכנה לשימוש!"

exit 0
