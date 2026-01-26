#!/bin/bash
#
# סקריפט להרצת טבלאות PWA בשרת
# הרצה: ./scripts/setup-pwa-tables.sh
#

# הגדרות
SSH_KEY="/Users/malkiel/projects/login/deploy_key"
SSH_HOST="mbeplusc@mbe-plus.com"
REMOTE_PATH="/home2/mbeplusc/public_html/form/login"

# צבעים
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}   הרצת סקריפטי SQL לטבלאות PWA${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""

# בקש פרטי MySQL
read -p "שם משתמש MySQL: " MYSQL_USER
read -sp "סיסמת MySQL: " MYSQL_PASS
echo ""
read -p "שם מסד הנתונים: " MYSQL_DB

echo ""
echo -e "${YELLOW}מתחבר לשרת...${NC}"

# הרצת הסקריפטים בשרת
ssh -i "$SSH_KEY" "$SSH_HOST" << ENDSSH
cd $REMOTE_PATH

echo ""
echo "=== יוצר טבלת user_tokens ==="
mysql -u $MYSQL_USER -p'$MYSQL_PASS' $MYSQL_DB < auth/sql/user_tokens.sql
if [ \$? -eq 0 ]; then
    echo "✅ user_tokens נוצר בהצלחה"
else
    echo "❌ שגיאה ביצירת user_tokens"
fi

echo ""
echo "=== יוצר טבלת user_credentials ==="
mysql -u $MYSQL_USER -p'$MYSQL_PASS' $MYSQL_DB < auth/sql/user_credentials.sql
if [ \$? -eq 0 ]; then
    echo "✅ user_credentials נוצר בהצלחה"
else
    echo "❌ שגיאה ביצירת user_credentials"
fi

echo ""
echo "=== יוצר טבלאות OTP ==="
mysql -u $MYSQL_USER -p'$MYSQL_PASS' $MYSQL_DB < auth/sql/otp_codes.sql
if [ \$? -eq 0 ]; then
    echo "✅ otp_codes ו-sms_rate_limits נוצרו בהצלחה"
else
    echo "❌ שגיאה ביצירת טבלאות OTP"
fi

echo ""
echo "=== בודק את הטבלאות שנוצרו ==="
mysql -u $MYSQL_USER -p'$MYSQL_PASS' $MYSQL_DB -e "SHOW TABLES LIKE '%token%'; SHOW TABLES LIKE '%credential%'; SHOW TABLES LIKE '%otp%'; SHOW TABLES LIKE '%rate%';"

ENDSSH

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   הסקריפט הסתיים!${NC}"
echo -e "${GREEN}========================================${NC}"
