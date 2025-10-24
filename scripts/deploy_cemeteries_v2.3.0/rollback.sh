#!/usr/bin/env bash
#
# קובץ: ~/public_html/form/login/scripts/deploy_cemeteries_v2.3.0/rollback.sh
# מטרה: שחזור לגרסה הקודמת במקרה של בעיה
# תאריך: 2025-10-24
# גרסה: v2.3.0
#
# שימוש: bash rollback.sh

set -euo pipefail

# ===== הגדרות נתיבים =====
PROJECT_ROOT=~/public_html/form/login
BACKUPS_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$PROJECT_ROOT/scripts/deploy_cemeteries_v2.3.0/deployment.log"
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

# ===== התחלת שחזור =====
log_message "🔄 מתחיל שחזור מגרסה $VERSION"

# ===== בדיקות =====
if [ ! -d "$BACKUPS_DIR" ]; then
    log_error "תיקיית גיבויים לא נמצאה: $BACKUPS_DIR"
    exit 1
fi

# חיפוש קבצי גיבוי מהגרסה הנוכחית
BACKUP_FILES=$(find "$BACKUPS_DIR" -name "*_backup_${DATE}_${VERSION}*.js" 2>/dev/null || true)

if [ -z "$BACKUP_FILES" ]; then
    log_error "לא נמצאו קבצי גיבוי מ-$DATE עבור $VERSION"
    echo ""
    echo "💡 טיפ: אולי הפריסה בוצעה בתאריך אחר?"
    echo "בדוק קבצים זמינים ב: $BACKUPS_DIR"
    exit 1
fi

log_message "נמצאו קבצי גיבוי:"
echo "$BACKUP_FILES" | while read -r backup; do
    log_message "  - $(basename "$backup")"
done

# ===== אישור מהמשתמש =====
echo ""
echo "⚠️  האם אתה בטוח שברצונך לשחזר את הקבצים?"
echo "פעולה זו תדרוס את הקבצים הנוכחיים!"
read -p "הקלד 'yes' לאישור: " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    log_message "שחזור בוטל על ידי המשתמש"
    exit 0
fi

# ===== שחזור קבצים =====
log_message "מתחיל שחזור קבצים..."

echo "$BACKUP_FILES" | while read -r BACKUP_FILE; do
    # חלץ את שם הקובץ המקורי
    BASENAME=$(basename "$BACKUP_FILE")
    
    # הסר את הסיומת _backup_DATE_VERSION
    ORIGINAL_NAME=$(echo "$BASENAME" | sed -E "s/_backup_[0-9-]+_v[0-9.]+b?//")
    
    # מצא את הנתיב המלא
    # (בהנחה שהקובץ הוא cemeteries-management.js)
    TARGET="$PROJECT_ROOT/dashboards/dashboard/cemeteries/assets/$ORIGINAL_NAME"
    
    if [ -f "$TARGET" ]; then
        cp -f "$BACKUP_FILE" "$TARGET"
        log_success "שוחזר: $TARGET"
    else
        log_error "נתיב יעד לא נמצא: $TARGET"
    fi
done

# ===== סיכום =====
log_message "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
log_success "שחזור הושלם!"
log_message "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo ""
echo "✅ שחזור בוצע בהצלחה!"
echo "📝 בדוק את הלוג: $LOG_FILE"
echo "🔄 רענן את הדפדפן ובדוק שהכל עובד"
echo ""

exit 0
