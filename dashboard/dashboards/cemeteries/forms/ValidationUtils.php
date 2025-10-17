<?php
class ValidationUtils {
    public static function validateIsraeliId($id) {
        $id = trim($id);
        if (strlen($id) != 9 || !ctype_digit($id)) return false;
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = intval($id[$i]);
            $digit *= (($i % 2) + 1);
            if ($digit > 9) {
                $digit = ($digit / 10) + ($digit % 10);
            }
            $sum += $digit;
        }
        return ($sum % 10 == 0);
    }
    
    public static function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 2) == '05') {
            return substr($phone, 0, 3) . '-' . substr($phone, 3);
        }
        return $phone;
    }
}
?>
