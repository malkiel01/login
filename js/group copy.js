// js/group.js
// כל ה-JavaScript של דף הקבוצה

// פונקציית מעבר בין טאבים
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

// ניהול משתתפים - פונקציות למנהל בלבד
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
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה בהסרת המשתתף');
        }
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
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה בביטול ההזמנה');
        }
    });
}

// ניהול קניות
function showAddPurchaseModal() {
    document.getElementById('addPurchaseModal').style.display = 'block';
}

function closeAddPurchaseModal() {
    document.getElementById('addPurchaseModal').style.display = 'none';
    document.getElementById('addPurchaseForm').reset();
    document.getElementById('imagePreview').style.display = 'none';
}

function previewImage(event) {
    const file = event.target.files[0];
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const preview = document.getElementById('imagePreview');
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    
    if (file) {
        reader.readAsDataURL(file);
    }
}

function deletePurchase(purchaseId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק קנייה זו?')) return;
    
    const formData = new FormData();
    formData.append('action', 'deletePurchase');
    formData.append('purchase_id', purchaseId);
    
    fetch('group.php?id=' + groupId, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'שגיאה במחיקת הקנייה');
        }
    });
}

// הצגת תמונה במודל
function showImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// אתחול Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // הוספת משתתף - רק למנהלים
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
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.reactivated) {
                        alert('המשתמש הופעל מחדש בהצלחה');
                    }
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בהוספת המשתתף');
                }
            });
        });
    }
    
    // עריכת משתתף - רק למנהלים
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
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בעדכון המשתתף');
                }
            });
        });
    }
    
    // הוספת קנייה
    const addPurchaseForm = document.getElementById('addPurchaseForm');
    if (addPurchaseForm) {
        addPurchaseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const memberId = document.getElementById('purchaseMember').value;
            if (!memberId) {
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
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בהוספת הקנייה');
                }
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