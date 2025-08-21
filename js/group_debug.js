// js/group_debug.js - גרסת דיבאג

// משתנים גלובליים (מוגדרים מה-HTML)
// const groupId, isOwner, availablePercentage

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

// Modal functions
function showAddMemberModal() {
    document.getElementById('addMemberModal').style.display = 'block';
}

function closeAddMemberModal() {
    document.getElementById('addMemberModal').style.display = 'none';
    document.getElementById('addMemberForm').reset();
}

// הוספת משתתף - גרסת דיבאג
document.addEventListener('DOMContentLoaded', function() {
    const addMemberForm = document.getElementById('addMemberForm');
    
    if (addMemberForm && isOwner) {
        addMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('=== ADD MEMBER DEBUG START ===');
            console.log('Form submitted');
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', document.getElementById('memberEmail').value);
            formData.append('nickname', document.getElementById('memberNickname').value);
            formData.append('participation_type', document.querySelector('input[name="participationType"]:checked').value);
            formData.append('participation_value', document.getElementById('memberValue').value);
            
            // הדפסת הנתונים שנשלחים
            console.log('Form data:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // בדיקה 1: שליחה למטפל הבדיקה
            console.log('Test 1: Sending to test handler...');
            fetch('test_ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Test handler response status:', response.status);
                console.log('Test handler response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Test handler raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Test handler parsed data:', data);
                    alert('בדיקה הצליחה! ראה console לפרטים');
                } catch(e) {
                    console.error('Test handler JSON parse error:', e);
                    alert('שגיאה בפענוח JSON - ראה console');
                }
            })
            .catch(error => {
                console.error('Test handler fetch error:', error);
            });
            
            // בדיקה 2: שליחה לקובץ המקורי
            setTimeout(() => {
                console.log('Test 2: Sending to original handler...');
                fetch('group.php?id=' + groupId, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Original handler response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Original handler raw response (first 500 chars):', text.substring(0, 500));
                    
                    // בדיקה אם זה HTML
                    if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
                        console.error('ERROR: Received HTML instead of JSON!');
                        console.log('Full HTML response saved to window.lastHTMLResponse');
                        window.lastHTMLResponse = text;
                        alert('שגיאה: התקבל HTML במקום JSON - ראה console');
                    } else {
                        try {
                            const data = JSON.parse(text);
                            console.log('Original handler parsed data:', data);
                            if (data.success) {
                                alert('הצלחה!');
                                location.reload();
                            } else {
                                alert('שגיאה: ' + (data.message || 'Unknown error'));
                            }
                        } catch(e) {
                            console.error('Original handler JSON parse error:', e);
                            alert('שגיאה בפענוח JSON מהשרת המקורי');
                        }
                    }
                })
                .catch(error => {
                    console.error('Original handler fetch error:', error);
                });
            }, 1000);
            
            console.log('=== ADD MEMBER DEBUG END ===');
        });
    }
    
    // בדיקת קיום האלמנטים
    console.log('Debug info:');
    console.log('- addMemberForm exists:', !!addMemberForm);
    console.log('- isOwner:', isOwner);
    console.log('- groupId:', groupId);
    console.log('- availablePercentage:', availablePercentage);
});

// פונקציות נוספות (stubs לבדיקה)
function toggleParticipationType() {
    console.log('toggleParticipationType called');
}

function editMember(memberId, type, value) {
    console.log('editMember called:', memberId, type, value);
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

function deletePurchase(purchaseId) {
    console.log('deletePurchase called:', purchaseId);
}

function showImageModal(src) {
    console.log('showImageModal called:', src);
}

function closeImageModal() {
    console.log('closeImageModal called');
}

// Close modals on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}