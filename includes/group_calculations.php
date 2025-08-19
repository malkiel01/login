<?php
// includes/group_calculations.php
// לוגיקת חישובים וחלוקת עלויות

function calculateGroupBalance($members, $purchases, $totalAmount) {
    if ($totalAmount == 0 || count($members) == 0) {
        return [
            'calculations' => [],
            'transfers' => [],
            'missingPercentage' => 0,
            'missingAmount' => 0,
            'fixedTotal' => 0,
            'percentageAmount' => 0
        ];
    }
    
    // הפרד בין אחוזים לסכומים קבועים
    $totalPercentage = 0;
    $fixedMembers = [];
    
    foreach ($members as $member) {
        if ($member['participation_type'] == 'percentage') {
            $totalPercentage += $member['participation_value'];
        } else {
            $fixedMembers[] = $member;
        }
    }
    
    // חישוב הסכום לאחר הפחתת סכומים קבועים
    $fixedTotal = array_sum(array_column($fixedMembers, 'participation_value'));
    $percentageAmount = max(0, $totalAmount - $fixedTotal);
    
    // אם סך האחוזים קטן מ-100%, חשב את החסר
    $missingPercentage = 0;
    $missingAmount = 0;
    if ($totalPercentage < 100 && $totalPercentage > 0) {
        $missingPercentage = 100 - $totalPercentage;
        $missingAmount = $percentageAmount * ($missingPercentage / 100);
    }
    
    // חישוב לכל משתתף
    $calculations = [];
    foreach ($members as $member) {
        $shouldPay = 0;
        
        if ($member['participation_type'] == 'percentage') {
            // חשב לפי האחוז האמיתי, לא לפי 100%
            $shouldPay = $percentageAmount * ($member['participation_value'] / 100);
        } else {
            $shouldPay = $member['participation_value'];
        }
        
        // סכום ששולם בפועל
        $actuallyPaid = 0;
        foreach ($purchases as $purchase) {
            if ($purchase['member_id'] == $member['id']) {
                $actuallyPaid += $purchase['amount'];
            }
        }
        
        $balance = $actuallyPaid - $shouldPay;
        
        $calculations[] = [
            'member' => $member,
            'shouldPay' => $shouldPay,
            'actuallyPaid' => $actuallyPaid,
            'balance' => $balance
        ];
    }
    
    // חישוב העברות נדרשות
    $transfers = calculateTransfers($calculations);
    
    return [
        'calculations' => $calculations,
        'transfers' => $transfers,
        'missingPercentage' => $missingPercentage,
        'missingAmount' => $missingAmount,
        'fixedTotal' => $fixedTotal,
        'percentageAmount' => $percentageAmount
    ];
}

function calculateTransfers($calculations) {
    $creditors = array_filter($calculations, function($c) { return $c['balance'] > 0; });
    $debtors = array_filter($calculations, function($c) { return $c['balance'] < 0; });
    
    usort($creditors, function($a, $b) { 
        return $b['balance'] <=> $a['balance']; 
    });
    usort($debtors, function($a, $b) { 
        return $a['balance'] <=> $b['balance']; 
    });
    
    $transfers = [];
    foreach ($creditors as &$creditor) {
        $remainingCredit = $creditor['balance'];
        
        foreach ($debtors as &$debtor) {
            if ($remainingCredit > 0.01 && $debtor['balance'] < -0.01) {
                $remainingDebt = abs($debtor['balance']);
                $transferAmount = min($remainingCredit, $remainingDebt);
                
                if ($transferAmount > 0.01) {
                    $transfers[] = [
                        'from' => $debtor['member']['nickname'],
                        'to' => $creditor['member']['nickname'],
                        'amount' => $transferAmount
                    ];
                    
                    $remainingCredit -= $transferAmount;
                    $debtor['balance'] += $transferAmount;
                }
            }
        }
    }
    
    return $transfers;
}

function renderCalculationsView($members, $purchases, $totalAmount) {
    $result = calculateGroupBalance($members, $purchases, $totalAmount);
    
    if ($totalAmount == 0 || count($members) == 0) {
        return '<div class="no-data">
            <i class="fas fa-info-circle"></i>
            <p>אין מספיק נתונים לחישוב</p>
            <p>יש להוסיף משתתפים וקניות</p>
        </div>';
    }
    
    ob_start();
    ?>
    <div class="calculation-summary">
        <div class="total-box">
            <i class="fas fa-receipt"></i>
            <h3>סכום כולל</h3>
            <div class="total-amount">₪<?php echo number_format($totalAmount, 2); ?></div>
        </div>
        
        <?php if ($result['fixedTotal'] > 0): ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p>סכומים קבועים: ₪<?php echo number_format($result['fixedTotal'], 2); ?></p>
            <p>סכום לחלוקה באחוזים: ₪<?php echo number_format($result['percentageAmount'], 2); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($result['missingPercentage'] > 0): ?>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>חסרים אחוזים להשלמה</h3>
            <p>מוגדר כרגע: <?php echo 100 - $result['missingPercentage']; ?>%</p>
            <p>חסרים: <?php echo $result['missingPercentage']; ?>%</p>
            <p>סכום לא מוקצה: ₪<?php echo number_format($result['missingAmount'], 2); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="members-calculations">
        <h3>סיכום לפי משתתף:</h3>
        <?php foreach ($result['calculations'] as $calc): ?>
        <div class="calc-card <?php echo $calc['balance'] >= 0 ? 'positive' : 'negative'; ?>">
            <div class="calc-member">
                <h4><?php echo htmlspecialchars($calc['member']['nickname']); ?></h4>
                <span class="participation-info">
                    <?php if ($calc['member']['participation_type'] == 'percentage'): ?>
                        <?php echo $calc['member']['participation_value']; ?>%
                    <?php else: ?>
                        ₪<?php echo number_format($calc['member']['participation_value'], 2); ?> קבוע
                    <?php endif; ?>
                </span>
            </div>
            <div class="calc-details">
                <div class="calc-row">
                    <span>צריך לשלם:</span>
                    <span>₪<?php echo number_format($calc['shouldPay'], 2); ?></span>
                </div>
                <div class="calc-row">
                    <span>שילם בפועל:</span>
                    <span>₪<?php echo number_format($calc['actuallyPaid'], 2); ?></span>
                </div>
                <div class="calc-row balance">
                    <span><?php echo $calc['balance'] >= 0 ? 'מגיע לו' : 'חייב'; ?>:</span>
                    <span>₪<?php echo number_format(abs($calc['balance']), 2); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="transfers-section">
        <h3>העברות נדרשות:</h3>
        <?php if (count($result['transfers']) > 0): ?>
            <?php foreach ($result['transfers'] as $transfer): ?>
            <div class="transfer-card">
                <div class="transfer-from">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($transfer['from']); ?>
                </div>
                <div class="transfer-arrow">
                    <i class="fas fa-arrow-left"></i>
                    <span>₪<?php echo number_format($transfer['amount'], 2); ?></span>
                </div>
                <div class="transfer-to">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($transfer['to']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-transfers">
                <i class="fas fa-check-circle"></i>
                <p>הכל מאוזן - אין העברות נדרשות!</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>