<?php
// ===== Small helper functions used by many pages =====

// Escape text before printing it in HTML
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Read one text value sent with POST (trimmed, empty string if missing)
function post($name) {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Store a one-time message (type: success or error) for the next page
function setFlash($type, $message) {
    $_SESSION['flash'] = [$type, $message];
}

// Print the stored message once, then forget it
function showFlash() {
    if (isset($_SESSION['flash'])) {
        echo '<div class="alert alert-' . e($_SESSION['flash'][0]) . '">' . e($_SESSION['flash'][1]) . '</div>';
        unset($_SESSION['flash']);
    }
}

// Go to another page inside the project
function redirect($path) {
    header('Location: ' . BASE_URL . '/' . $path);
    exit;
}

// ===== Blood types and compatibility =====

// All blood types
function bloodTypes() {
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

// Compatibility table: key = type the patient needs, value = types that can be given
function compatibleTypes($needed) {
    $table = [
        'A+'  => ['A+', 'A-', 'O+', 'O-'],
        'A-'  => ['A-', 'O-'],
        'B+'  => ['B+', 'B-', 'O+', 'O-'],
        'B-'  => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+'  => ['O+', 'O-'],
        'O-'  => ['O-'],
    ];
    return $table[$needed] ?? [];
}

// <option> list of blood types, with one selected
function bloodTypeOptions($selected = '') {
    $html = '<option value="">-- Select --</option>';
    foreach (bloodTypes() as $type) {
        $isSelected = ($type === $selected) ? ' selected' : '';
        $html .= '<option value="' . e($type) . '"' . $isSelected . '>' . e($type) . '</option>';
    }
    return $html;
}

// ===== Stock levels and low-stock alerts =====

// Return OK, LOW or CRITICAL for a number of units
function stockLevel($units) {
    if ($units < STOCK_LIMITS['CRITICAL']) { return 'CRITICAL'; }
    if ($units < STOCK_LIMITS['LOW']) { return 'LOW'; }
    return 'OK';
}

// Small coloured label for a stock level
function levelBadge($units) {
    $level = stockLevel($units);
    return '<span class="badge badge-' . strtolower($level) . '">' . $level . '</span>';
}

// Small coloured label for a request/pledge status
function statusBadge($status) {
    return '<span class="badge status-' . strtolower(e($status)) . '">' . e($status) . '</span>';
}

// Low-stock alerts, worked out live from blood_stock (no separate table).
// A donor sees only the low blood types their own type can donate to.
// $donorType = null means "show every low type" (used for visitors).
function getAlertsForDonor($pdo, $donorType) {
    // Blood types this donor can give to
    $types = [];
    foreach (bloodTypes() as $type) {
        if ($donorType === null || in_array($donorType, compatibleTypes($type))) {
            $types[] = $type;
        }
    }
    $marks = implode(',', array_fill(0, count($types), '?'));
    $sql = "SELECT s.blood_type, s.units, s.hospital_id, u.name AS hospital_name
            FROM blood_stock s
            JOIN users u ON u.id = s.hospital_id
            WHERE s.units < ? AND s.blood_type IN ($marks)
            ORDER BY s.units ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([STOCK_LIMITS['LOW']], $types));
    return $stmt->fetchAll();
}

// Next date a donor may donate again (null = can donate now)
function nextEligibleDate($pdo, $donorId) {
    $stmt = $pdo->prepare("SELECT MAX(donated_on) FROM donation_pledges WHERE donor_id = ? AND status = 'Confirmed'");
    $stmt->execute([$donorId]);
    $last = $stmt->fetchColumn();
    if (!$last) {
        return null;
    }
    $date = new DateTime($last);
    $date->modify('+' . DONATION_GAP_MONTHS . ' months');
    return $date->format('Y-m-d');
}
