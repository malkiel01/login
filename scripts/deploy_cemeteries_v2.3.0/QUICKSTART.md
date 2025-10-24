# 🚀 הוראות הרצה מהירות - תיקון בעיית טעינת בתי עלמין

## בעיה שמתוקנת
```
Table not found: null
```
בתי עלמין לא נטענים בגלל שהטבלה #mainTable לא קיימת ברגע האתחול.

---

## פתרון במהירות ⚡

### 1. העתק לשרת
```bash
scp -r deploy_cemeteries_v2.3.0 user@server:~/public_html/form/login/scripts/
```

### 2. הרץ פריסה
```bash
ssh user@server
cd ~/public_html/form/login/scripts/deploy_cemeteries_v2.3.0
chmod +x deploy.sh rollback.sh
bash deploy.sh
```

### 3. בדוק
- רענן דפדפן (Ctrl+Shift+R)
- לחץ על "בתי עלמין"
- וודא שהטבלה נטענת ✅

---

## אם יש בעיה 🔄
```bash
bash rollback.sh
```

---

## מה השתנה?
- ✅ הוספת בדיקה `ensureMainTableExists()` לפני אתחול TableManager
- ✅ בנייה אוטומטית של #mainTable אם הוא לא קיים
- ✅ שיפור הודעות debug

---

## קובץ שגובה
```
backups/cemeteries-management_backup_2025-10-24_v2.3.0.js
```

---

**זהו! פשוט וקצר.** 📦
