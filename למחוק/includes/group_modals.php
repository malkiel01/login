<?php
// includes/group_modals.php
// כל ה-Modals של דף הקבוצה

function renderAddMemberModal($available_percentage) {
    ?>
    <div id="addMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>הוספת משתתף חדש</h2>
                <span class="close" onclick="closeAddMemberModal()">&times;</span>
            </div>
            <form id="addMemberForm">
                <div class="form-group">
                    <label for="memberEmail">אימייל:</label>
                    <input type="email" id="memberEmail" required>
                </div>
                <div class="form-group">
                    <label for="memberNickname">כינוי:</label>
                    <input type="text" id="memberNickname" required>
                </div>
                <div class="form-group">
                    <label>סוג השתתפות:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="participationType" value="percentage" checked onchange="toggleParticipationType()">
                            אחוז
                        </label>
                        <label>
                            <input type="radio" name="participationType" value="fixed" onchange="toggleParticipationType()">
                            סכום קבוע
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="memberValue">ערך השתתפות:</label>
                    <div class="input-with-suffix">
                        <input type="number" id="memberValue" step="0.01" required>
                        <span id="valueSuffix">%</span>
                    </div>
                    <small id="percentageInfo" class="form-hint">
                        נותרו <?php echo $available_percentage; ?>% זמינים להקצאה
                    </small>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> הוסף
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddMemberModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function renderEditMemberModal() {
    ?>
    <div id="editMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>עריכת פרטי משתתף</h2>
                <span class="close" onclick="closeEditMemberModal()">&times;</span>
            </div>
            <form id="editMemberForm">
                <input type="hidden" id="editMemberId">
                <div class="form-group">
                    <label>סוג השתתפות:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="editParticipationType" value="percentage" onchange="toggleEditParticipationType()">
                            אחוז
                        </label>
                        <label>
                            <input type="radio" name="editParticipationType" value="fixed" onchange="toggleEditParticipationType()">
                            סכום קבוע
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editMemberValue">ערך השתתפות:</label>
                    <div class="input-with-suffix">
                        <input type="number" id="editMemberValue" step="0.01" required>
                        <span id="editValueSuffix">%</span>
                    </div>
                    <small id="editPercentageInfo" class="form-hint"></small>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> שמור
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditMemberModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function renderAddPurchaseModal($members, $is_owner, $member_id) {
    ?>
    <div id="addPurchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>הוספת קנייה חדשה</h2>
                <span class="close" onclick="closeAddPurchaseModal()">&times;</span>
            </div>
            <form id="addPurchaseForm" enctype="multipart/form-data">
                <?php if ($is_owner): ?>
                <div class="form-group">
                    <label for="purchaseMember">בחר משתתף:</label>
                    <select id="purchaseMember" required>
                        <option value="">בחר משתתף...</option>
                        <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['nickname']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <!-- משתמש רגיל - הקנייה תירשם על שמו בלבד -->
                <input type="hidden" id="purchaseMember" value="<?php echo $member_id; ?>">
                <div class="form-group">
                    <label>הקנייה תירשם על שמך</label>
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i>
                        רק מנהל הקבוצה יכול להוסיף קניות בשם משתתפים אחרים
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="purchaseAmount">סכום הקנייה (₪):</label>
                    <input type="number" id="purchaseAmount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="purchaseDescription">תיאור המוצרים:</label>
                    <textarea id="purchaseDescription" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="purchaseImage">תמונת קבלה:</label>
                    <input type="file" id="purchaseImage" accept="image/*" onchange="previewImage(event)">
                    <img id="imagePreview" class="image-preview" style="display: none;">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> הוסף קנייה
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddPurchaseModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function renderImageModal() {
    ?>
    <div id="imageModal" class="modal">
        <div class="modal-content image-modal">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="">
        </div>
    </div>
    <?php
}
?>