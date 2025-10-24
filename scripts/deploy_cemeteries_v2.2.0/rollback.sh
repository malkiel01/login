#!/usr/bin/env bash
# קובץ: $HOME/public_html/form/login/scripts/deploy_cemeteries_v2.2.0/rollback.sh
# מטרה: שחזור לגרסה הקודמת לפני הפריסה
# תאריך: 2025-10-24
# גרסה: v2.2.0

set -euo pipefail

# ========================================
# הגדרות בסיסיות - עם $HOME
# ========================================
PROJECT_ROOT="$HOME/public_html/form/login"
DEPLOY_DIR="$PROJECT_ROOT/scripts/deploy_cemeteries_v2.2.0"
BACKUPS_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$DEPLOY_DIR/rollback.log"
VERSION="v2.2.0"
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
# התחלת שחזור
# ========================================
log "🔄 ========================================="
log "🔄 התחלת שחזור (rollback) $VERSION"
log "🔄 ========================================="
log "📂 נתיב פרויקט: $PROJECT_ROOT"

# בדיקות
[ -d "$PROJECT_ROOT" ] || error_exit "נתיב פרויקט לא קיים"
[ -d "$BACKUPS_DIR" ] || error_exit "תיקיית גיבויים לא קיימת"

# אישור מהמשתמש
log "⚠️  פעולה זו תשחזר את הקבצים לגרסה הקודמת"
log "⚠️  האם להמשיך? (הקש Enter להמשך, Ctrl+C לביטול)"
read -r

log "🔍 מחפש קבצי גיבוי..."

FILES_RESTORED=0

# שחזר cemeteries-management.js
BACKUP_FILE=$(ls -t "$BACKUPS_DIR"/cemeteries-management_backup_*_${VERSION}.js 2>/dev/null | head -1)

if [ -n "$BACKUP_FILE" ] && [ -f "$BACKUP_FILE" ]; then
    TARGET="$PROJECT_ROOT/dashboard/dashboards/cemeteries/js/cemeteries-management.js"
    
    log "📄 משחזר: $(basename "$BACKUP_FILE")"
    cp -f "$BACKUP_FILE" "$TARGET" || error_exit "כשל בשחזור: $BACKUP_FILE"
    log "  ✅ שוחזר בהצלחה ל: $TARGET"
    
    FILES_RESTORED=$((FILES_RESTORED + 1))
else
    log "  ⚠️ לא נמצא גיבוי עבור cemeteries-management.js"
fi

# ========================================
# סיכום
# ========================================
log "🎉 ========================================="
log "🎉 שחזור הסתיים!"
log "🎉 ========================================="
log "📊 סטטיסטיקות:"
log "  • קבצים ששוחזרו: $FILES_RESTORED"
log ""
log "📝 הערות:"
log "  • המערכת שוחזרה למצב הקודם"
log "  • רענן את הדפדפן (Ctrl+F5) לראות שינויים"
log ""
log "✅ השחזור הושלם!"

exit 0
