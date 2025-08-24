// js/group.js - גרסה סופית

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

// ניהול משתתפים
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

// עריכת משתתף
function editMember(memberId, type, value) {
    document.getElementById('editMemberId').value = memberId;
    document.querySelector(`input[name="editParticipationType"][value="${type}"]`).checked = true;
    document.getElementById('editMemberValue').value = value;
    toggleEditParticipationType();
    document.getElementById('editMemberModal').style.display = 'block';
}

function closeEditMemberModal() {
    document.getElementById('editMemberModal').style.display = 'none';
    document.getElementById('editMemberForm').reset();
}

function toggleEditParticipationType() {
    const type = document.querySelector('input[name="editParticipationType"]:checked').value;
    const suffix = document.getElementById('editValueSuffix');
    const info = document.getElementById('editPercentageInfo');
    
    suffix.textContent = type === 'percentage' ? '%' : '₪';
    
    if (type === 'percentage') {
        info.textContent = `נותרו ${availablePercentage}% זמינים (בנוסף לערך הנוכחי)`;
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
}

function removeMember(memberId) {
    if (!confirm('האם אתה בטוח שברצונך להסיר משתתף זה?')) return;
    
    const formData = new FormData();
    formData.append('action', 'removeMember');
    formData.append('member_id', memberId);
    
    fetch('group.php?id=' + groupId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה בהסרת המשתתף');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('שגיאה בתקשורת עם השרת');
    });
}

// ביטול הזמנה
function cancelInvitation(invitationId) {
    if (!confirm('האם אתה בטוח שברצונך לבטל הזמנה זו?')) return;
    
    const formData = new FormData();
    formData.append('action', 'cancelInvitation');
    formData.append('invitation_id', invitationId);
    
    fetch('group.php?id=' + groupId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה בביטול ההזמנה');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('שגיאה בתקשורת עם השרת');
    });
}

// ניהול קניות
function showAddPurchaseModal() {
    document.getElementById('addPurchaseModal').style.display = 'block';
}

function closeAddPurchaseModal() {
    document.getElementById('addPurchaseModal').style.display = 'none';
    document.getElementById('addPurchaseForm').reset();
    const preview = document.getElementById('imagePreview');
    if (preview) {
        preview.style.display = 'none';
    }
}

function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('imagePreview');
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function deletePurchase(purchaseId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק קנייה זו?')) return;
    
    const formData = new FormData();
    formData.append('action', 'deletePurchase');
    formData.append('purchase_id', purchaseId);
    
    fetch('group.php?id=' + groupId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה במחיקת הקנייה');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('שגיאה בתקשורת עם השרת');
    });
}

// הצגת תמונה במודל
function showImageModal(src) {
    const modal = document.getElementById('imageModal');
    const img = document.getElementById('modalImage');
    if (modal && img) {
        img.src = src;
        modal.style.display = 'block';
    }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// אתחול Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // הוספת משתתף
    const addMemberForm = document.getElementById('addMemberForm');
    if (addMemberForm && isOwner) {
        addMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const participationType = document.querySelector('input[name="participationType"]:checked').value;
            const participationValue = parseFloat(document.getElementById('memberValue').value);
            
            // בדיקת תקינות בצד הלקוח
            if (participationType === 'percentage' && participationValue > availablePercentage) {
                alert(`לא ניתן להוסיף יותר מ-${availablePercentage}% זמינים`);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', document.getElementById('memberEmail').value);
            formData.append('nickname', document.getElementById('memberNickname').value);
            formData.append('participation_type', participationType);
            formData.append('participation_value', participationValue);
            
            // fetch('group.php?id=' + groupId, {
            //     method: 'POST',
            //     headers: {
            //         'X-Requested-With': 'XMLHttpRequest'
            //     },
            //     body: formData
            // })
            // .then(response => {
            //     // קרא את התגובה כטקסט תחילה
            //     return response.text().then(text => {
            //         // הדפס לקונסול לדיבאג
            //         console.log('Raw response:', text);
                    
            //         // נסה לפרסר ל-JSON
            //         try {
            //             return JSON.parse(text);
            //         } catch (e) {
            //             // אם לא הצליח לפרסר, הצג את הטקסט
            //             console.error('Failed to parse JSON:', text);
                        
            //             // בדוק אם זו שגיאת PHP
            //             if (text.includes('Fatal error') || text.includes('Warning') || text.includes('Parse error')) {
            //                 throw new Error('PHP Error: ' + text.substring(0, 200));
            //             }
                        
            //             // אם זה ריק
            //             if (text.trim() === '') {
            //                 throw new Error('Empty response from server');
            //             }
                        
            //             throw new Error('Invalid JSON response');
            //         }
            //     });
            // })
            // .then(data => {
            //     if (data.success) {
            //         // צור פופאפ פשוט
            //         const popup = document.createElement('div');
            //         popup.style.cssText = `
            //             position: fixed;
            //             top: 50%;
            //             left: 50%;
            //             transform: translate(-50%, -50%);
            //             background: white;
            //             border-radius: 15px;
            //             padding: 30px;
            //             box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            //             z-index: 10000;
            //             text-align: center;
            //         `;
                    
            //         const icon = data.notification_sent ? '✅' : '⚠️';
            //         const color = data.notification_sent ? '#28a745' : '#ffc107';
                    
            //         popup.innerHTML = `
            //             <div style="font-size: 60px; margin-bottom: 20px;">${icon}</div>
            //             <h2 style="color: #333; margin-bottom: 15px;">הזמנה נשלחה!</h2>
            //             <p style="color: #666; font-size: 16px;">
            //                 ${data.message}
            //             </p>
            //             <button onclick="this.parentElement.remove(); location.reload();" 
            //                     style="background: ${color}; color: white; border: none; 
            //                         padding: 12px 30px; border-radius: 8px; 
            //                         font-size: 16px; cursor: pointer; margin-top: 20px;">
            //                 סגור
            //             </button>
            //         `;
                    
            //         document.body.appendChild(popup);
            //         closeAddMemberModal();
                    
            //     } else {
            //         alert(data.message || 'שגיאה בהוספת המשתתף');
            //     }
            // })
            // .catch(error => {
            //     console.error('Error details:', error);
                
            //     // הצג הודעה ידידותית למשתמש
            //     alert('אירעה שגיאה בהוספת המשתתף. אנא נסה שוב.\n\nפרטי השגיאה:\n' + error.message);
            // });

            // -------------------------

            fetch('group.php?id=' + groupId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            //         // רענון הדף בשקט אחרי הצלחה
            //         location.reload();
                    // הצג פופאפ מפורט
                    showNotificationPopup(data);
                    
                    // רענן את הדף אחרי 3 שניות
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    // הצגת הודעת שגיאה רק במקרה של כישלון
                    alert(data.message || 'שגיאה בהוספת המשתתף');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת: ' + error.message);
            });
        });
    }

    // פונקציה להצגת פופאפ מפורט
    function showNotificationPopup(data) {
        // יצירת אלמנט המודל
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s;
        `;
        
        // תוכן המודל
        const content = document.createElement('div');
        content.style.cssText = `
            background: white;
            border-radius: 15px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s;
            overflow: hidden;
        `;
        
        // כותרת
        const header = document.createElement('div');
        header.style.cssText = `
            background: ${data.notification_sent ? 
                'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 
                'linear-gradient(135deg, #ffc107 0%, #ff9800 100%)'};
            color: white;
            padding: 20px;
            text-align: center;
        `;
        
        const icon = data.notification_sent ? '✅' : '⚠️';
        const title = data.notification_sent ? 
            'הזמנה והתראה נשלחו בהצלחה!' : 
            'הזמנה נשלחה (ללא התראה)';
        
        header.innerHTML = `
            <div style="font-size: 50px; margin-bottom: 10px;">${icon}</div>
            <h2 style="margin: 0; font-size: 20px;">${title}</h2>
        `;
        
        // גוף ההודעה
        const body = document.createElement('div');
        body.style.cssText = 'padding: 20px;';
        
        let bodyHTML = `
            <div style="margin-bottom: 20px;">
                <p style="color: #333; font-size: 16px; margin: 10px 0;">
                    ${data.message}
                </p>
            </div>
        `;
        
        // פרטים נוספים
        if (data.details) {
            bodyHTML += `
                <div style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px;">
                    <h4 style="color: #667eea; margin: 0 0 10px 0;">📊 פרטי ההזמנה:</h4>
                    <ul style="margin: 0; padding-right: 20px; color: #666;">
                        <li>מזהה הזמנה: #${data.details.invitation_id}</li>
                        ${data.details.user_exists ? 
                            `<li>משתמש רשום: ${data.details.user_name || 'כן'}</li>` : 
                            '<li>משתמש חדש (טרם נרשם)</li>'}
                    </ul>
                </div>
            `;
            
            if (data.notification_sent && data.details.notification_details) {
                bodyHTML += `
                    <div style="background: #d4edda; border-radius: 10px; padding: 15px; margin-bottom: 15px;">
                        <h4 style="color: #28a745; margin: 0 0 10px 0;">🔔 פרטי ההתראה:</h4>
                        <ul style="margin: 0; padding-right: 20px; color: #155724;">
                            ${data.details.notification_details.queue_id ? 
                                `<li>מזהה תור: #${data.details.notification_details.queue_id}</li>` : ''}
                            ${data.details.notification_details.immediately_sent ? 
                                '<li>סטטוס: נשלחה מיידית!</li>' : 
                                '<li>סטטוס: ממתינה בתור</li>'}
                        </ul>
                    </div>
                `;
            }
            
            if (data.details.saved_in_tables && data.details.saved_in_tables.length > 0) {
                bodyHTML += `
                    <div style="background: #d1ecf1; border-radius: 10px; padding: 15px;">
                        <h4 style="color: #0c5460; margin: 0 0 10px 0;">💾 נשמר בטבלאות:</h4>
                        <ul style="margin: 0; padding-right: 20px; color: #0c5460;">
                            ${data.details.saved_in_tables.map(table => 
                                `<li>${table}</li>`
                            ).join('')}
                        </ul>
                    </div>
                `;
            }
        }
        
        // מידע דיבאג (רק אם יש)
        if (data.debug && window.location.search.includes('debug=1')) {
            bodyHTML += `
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #666; font-size: 14px;">
                        🔍 מידע דיבאג (למפתחים)
                    </summary>
                    <pre style="background: #263238; color: #aed581; padding: 10px; 
                        border-radius: 5px; margin-top: 10px; font-size: 12px; 
                        overflow-x: auto; direction: ltr;">
    ${JSON.stringify(data.debug, null, 2)}
                    </pre>
                </details>
            `;
        }
        
        body.innerHTML = bodyHTML;
        
        // כפתור סגירה
        const footer = document.createElement('div');
        footer.style.cssText = 'padding: 20px; text-align: center;';
        footer.innerHTML = `
            <button onclick="this.closest('[style*=fixed]').remove()" 
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white; border: none; padding: 12px 30px;
                        border-radius: 8px; font-size: 16px; cursor: pointer;
                        font-weight: 600;">
                סגור
            </button>
        `;
        
        // הרכבת המודל
        content.appendChild(header);
        content.appendChild(body);
        content.appendChild(footer);
        modal.appendChild(content);
        
        // הוספה לדף
        document.body.appendChild(modal);
        
        // אנימציות CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideUp {
                from { transform: translateY(50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }

    // הוסף גם console.log לדיבאג
    console.log('Group.js loaded with notification popup support');

    
    // עריכת משתתף
    const editMemberForm = document.getElementById('editMemberForm');
    if (editMemberForm && isOwner) {
        editMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'editMember');
            formData.append('member_id', document.getElementById('editMemberId').value);
            formData.append('participation_type', document.querySelector('input[name="editParticipationType"]:checked').value);
            formData.append('participation_value', document.getElementById('editMemberValue').value);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בעדכון המשתתף');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
        });
    }
    
    // הוספת קנייה
    const addPurchaseForm = document.getElementById('addPurchaseForm');
    if (addPurchaseForm) {
        addPurchaseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const memberId = document.getElementById('purchaseMember').value;
            if (!memberId && isOwner) {
                alert('יש לבחור משתתף');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'addPurchase');
            formData.append('member_id', memberId);
            formData.append('amount', document.getElementById('purchaseAmount').value);
            formData.append('description', document.getElementById('purchaseDescription').value);
            
            const imageFile = document.getElementById('purchaseImage').files[0];
            if (imageFile) {
                formData.append('image', imageFile);
            }
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בהוספת הקנייה');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
        });
    }
    
    // סגירת modals בלחיצה מחוץ להם
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
});