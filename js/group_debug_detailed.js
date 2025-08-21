// js/group_debug_detailed.js - גרסת דיבאג עם הצגת שגיאות מפורטת

// פונקציות בסיסיות
function showTab(tabName, element) {
    document.querySelectorAll('.content').forEach(content => {
        content.style.display = 'none';
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.getElementById(tabName).style.display = 'block';
    element.classList.add('active');
}

function showAddMemberModal() {
    document.getElementById('addMemberModal').style.display = 'block';
    updatePercentageInfo();
}

function closeAddMemberModal() {
    document.getElementById('addMemberModal').style.display = 'none';
    document.getElementById('addMemberForm').reset();
}

function toggleParticipationType() {
    const type = document.querySelector('input[name="participationType"]:checked').value;
    const suffix = document.getElementById('valueSuffix');
    const info = document.getElementById('percentageInfo');
    
    suffix.textContent = type === 'percentage' ? '%' : '₪';
    
    if (type === 'percentage') {
        info.style.display = 'block';
        document.getElementById('memberValue').max = availablePercentage;
    } else {
        info.style.display = 'none';
        document.getElementById('memberValue').removeAttribute('max');
    }
}

function updatePercentageInfo() {
    const type = document.querySelector('input[name="participationType"]:checked')?.value;
    if (type === 'percentage') {
        document.getElementById('percentageInfo').style.display = 'block';
    }
}

// פונקציות stub
function editMember(memberId, type, value) {
    console.log('editMember called:', memberId, type, value);
}

function closeEditMemberModal() {
    console.log('closeEditMemberModal called');
}

function toggleEditParticipationType() {
    console.log('toggleEditParticipationType called');
}

function removeMember(memberId) {
    console.log('removeMember called:', memberId);
}

function cancelInvitation(invitationId) {
    console.log('cancelInvitation called:', invitationId);
}

function showAddPurchaseModal() {
    console.log('showAddPurchaseModal called');
}

function closeAddPurchaseModal() {
    console.log('closeAddPurchaseModal called');
}

function previewImage(event) {
    console.log('previewImage called');
}

function deletePurchase(purchaseId) {
    console.log('deletePurchase called:', purchaseId);
}

function showImageModal(src) {
    console.log('showImageModal called:', src);
}

function closeImageModal() {
    console.log('closeImageModal called');
}

// אתחול Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // הוספת משתתף עם דיבאג מפורט
    const addMemberForm = document.getElementById('addMemberForm');
    if (addMemberForm && isOwner) {
        addMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const participationType = document.querySelector('input[name="participationType"]:checked').value;
            const participationValue = parseFloat(document.getElementById('memberValue').value);
            const email = document.getElementById('memberEmail').value;
            const nickname = document.getElementById('memberNickname').value;
            
            // בדיקת תקינות בצד הלקוח
            if (participationType === 'percentage' && participationValue > availablePercentage) {
                alert(`לא ניתן להוסיף יותר מ-${availablePercentage}% זמינים`);
                return;
            }
            
            // יצירת FormData
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', email);
            formData.append('nickname', nickname);
            formData.append('participation_type', participationType);
            formData.append('participation_value', participationValue);
            
            // הצגת הנתונים שנשלחים
            let debugInfo = "=== נתונים שנשלחים ===\n";
            debugInfo += `URL: group.php?id=${groupId}\n`;
            debugInfo += `Email: ${email}\n`;
            debugInfo += `Nickname: ${nickname}\n`;
            debugInfo += `Type: ${participationType}\n`;
            debugInfo += `Value: ${participationValue}\n`;
            console.log(debugInfo);
            
            // שליחת הבקשה
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // שמירת פרטי התגובה
                const contentType = response.headers.get("content-type");
                const status = response.status;
                const statusText = response.statusText;
                
                let responseDebug = "=== תגובה מהשרת ===\n";
                responseDebug += `Status: ${status} ${statusText}\n`;
                responseDebug += `Content-Type: ${contentType}\n`;
                
                // קריאת התגובה כטקסט
                return response.text().then(text => {
                    responseDebug += `\n=== תוכן התגובה ===\n`;
                    
                    // בדיקה אם זה JSON
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        try {
                            const data = JSON.parse(text);
                            responseDebug += "JSON התקבל בהצלחה:\n";
                            responseDebug += JSON.stringify(data, null, 2);
                            
                            // הצגת התוצאה
                            alert(responseDebug);
                            
                            if (data.success) {
                                location.reload();
                            }
                            
                            return data;
                        } catch (e) {
                            responseDebug += "שגיאה בפענוח JSON:\n";
                            responseDebug += e.message + "\n\n";
                            responseDebug += "טקסט שהתקבל:\n";
                            responseDebug += text.substring(0, 1000);
                        }
                    } else {
                        responseDebug += "התקבל HTML במקום JSON!\n\n";
                        responseDebug += "תחילת ה-HTML:\n";
                        responseDebug += text.substring(0, 500);
                        
                        // בדיקה אם זה דף dashboard
                        if (text.includes('dashboard') || text.includes('קבוצות הרכישה שלי')) {
                            responseDebug += "\n\n⚠️ נראה שהופנית לדף dashboard!";
                            responseDebug += "\nסיבות אפשריות:";
                            responseDebug += "\n1. אין קבוצה עם ID " + groupId;
                            responseDebug += "\n2. אינך חבר בקבוצה זו";
                            responseDebug += "\n3. הקבוצה לא פעילה";
                        }
                    }
                    
                    // הצגת כל המידע
                    alert(responseDebug);
                    
                    // שמירה בקונסול
                    console.log(responseDebug);
                    console.log("Full response:", text);
                    
                    throw new Error("לא התקבל JSON תקין");
                });
            })
            .catch(error => {
                let errorMsg = "=== שגיאת תקשורת ===\n";
                errorMsg += `Error: ${error.message}\n`;
                errorMsg += `\nבדוק:\n`;
                errorMsg += `1. האם הקבוצה ${groupId} קיימת?\n`;
                errorMsg += `2. האם אתה חבר בקבוצה?\n`;
                errorMsg += `3. האם אתה מחובר למערכת?\n`;
                
                alert(errorMsg);
                console.error('Full error:', error);
            });
        });
    }
    
    // בדיקת פרטי הדף
    console.log('=== פרטי הדף ===');
    console.log('Group ID:', typeof groupId !== 'undefined' ? groupId : 'NOT DEFINED');
    console.log('Is Owner:', typeof isOwner !== 'undefined' ? isOwner : 'NOT DEFINED');
    console.log('Available Percentage:', typeof availablePercentage !== 'undefined' ? availablePercentage : 'NOT DEFINED');
    console.log('Add Member Form exists:', !!addMemberForm);
    
    // סגירת modals בלחיצה מחוץ להם
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
});