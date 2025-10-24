#!/usr/bin/env bash
#
# קובץ: ~/public_html/form/login/scripts/deploy_cemeteries_v2.3.0/deploy.sh
# מטרה: פריסת תיקון בעיית טעינת בתי עלמין - "Table not found: null"
# תאריך: 2025-10-24
# גרסה: v2.3.0
# 
# שלבי הפעולה:
# 1. בדיקת תקינות נתיבים
# 2. יצירת גיבוי של cemeteries-management.js הקיים
# 3. העתקת הקובץ המעודכן ממחסן payload
# 4. תיעוד כל שלב ב-deployment.log
#
# הוראות שחזור:
# במקרה של בעיה, הרץ: bash rollback.sh

set -euo pipefail

# ===== הגדרות נתיבים =====
PROJECT_ROOT=~/public_html/form/login
DEPLOY_DIR="$PROJECT_ROOT/scripts/deploy_cemeteries_v2.3.0"
PAYLOAD_DIR="$DEPLOY_DIR/payload"
BACKUPS_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$DEPLOY_DIR/deployment.log"
VERSION="v2.3.0"
DATE=$(date '+%Y-%m-%d')
TIMESTAMP=$(date '+%F %T')

# ===== פונקציות עזר =====
log_message() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$TIMESTAMP] ❌ שגיאה: $1" | tee -a "$LOG_FILE" >&2
}

log_success() {
    echo "[$TIMESTAMP] ✅ $1" | tee -a "$LOG_FILE"
}

# ===== התחלת פריסה =====
log_message "🚀 התחלת פריסה $VERSION"
log_message "נתיב פרויקט: $PROJECT_ROOT"

# ===== בדיקות קיום נתיבים =====
if [ ! -d "$PROJECT_ROOT" ]; then
    log_error "נתיב פרויקט לא קיים: $PROJECT_ROOT"
    exit 1
fi

if [ ! -d "$PAYLOAD_DIR" ]; then
    log_error "תיקיית payload לא קיימת: $PAYLOAD_DIR"
    exit 1
fi

log_success "כל הנתיבים תקינים"

# יצירת תיקיית גיבויים אם לא קיימת
mkdir -p "$BACKUPS_DIR"
log_success "תיקיית גיבויים מוכנה: $BACKUPS_DIR"

# ===== עיבוד קבצים =====
log_message "מתחיל עיבוד קבצים..."

# מעבר על כל הקבצים ב-payload
cd "$PAYLOAD_DIR"

while IFS= read -r -d '' FILE; do
    # חישוב נתיב יחסי
    REL_PATH="${FILE#./}"
    TARGET="$PROJECT_ROOT/$REL_PATH"
    
    # פרטי הקובץ
    BASENAME="$(basename "$REL_PATH")"
    EXT="${BASENAME##*.}"
    NAME="${BASENAME%.*}"
    
    log_message "מעבד: $REL_PATH"
    
    # בדיקה אם הקובץ קיים במערכת
    if [ -f "$TARGET" ]; then
        # יצירת גיבוי
        BK="$BACKUPS_DIR/${NAME}_backup_${DATE}_${VERSION}.${EXT}"
        
        # אם הגיבוי כבר קיים, הוסף סיומת
        if [ -f "$BK" ]; then
            BK="$BACKUPS_DIR/${NAME}_backup_${DATE}_${VERSION}b.${EXT}"
        fi
        
        cp -f "$TARGET" "$BK"
        log_success "גיבוי נוצר: $BK"
    else
        # קובץ חדש - צור את התיקייה אם צריך
        log_message "קובץ חדש (לא היה קיים קודם): $TARGET"
        mkdir -p "$(dirname "$TARGET")"
    fi
    
    # העתקת הקובץ החדש
    cp -f "$FILE" "$TARGET"
    log_success "קובץ הוחלף: $REL_PATH"
    
done < <(find . -type f -print0)

# ===== סיכום =====
log_message "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
log_success "פריסה הסתיימה בהצלחה! $VERSION"
log_message "תאריך: $DATE"
log_message "גיבויים נשמרו ב: $BACKUPS_DIR"
log_message "לוג מלא ב: $LOG_FILE"
log_message "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo ""
echo "✅ פריסה הושלמה!"
echo "📝 בדוק את הלוג: $LOG_FILE"
echo "🔄 לשחזור במקרה של בעיה: bash rollback.sh"
echo ""

exit 0
