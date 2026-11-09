<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('hospital');
$hospitalId = $_SESSION['user_id'];
$types = bloodTypes();
$errors = [];

// ===== Handle every button on this page (the hidden "action" field says which one) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // --- Save stock: blank box = leave that blood type unchanged ---
    if ($action === 'save_stock') {
        $newUnits = [];
        foreach ($types as $i => $type) {
            $raw = $_POST['units'][$i] ?? '';
            $raw = is_string($raw) ? trim($raw) : '';
            if ($raw === '') { continue; }
            $number = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
            if ($number === false) {
                $errors[] = $type . ': units must be a whole number from 0 to 9999.';
            } else {
                $newUnits[$type] = $number;
            }
        }
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO blood_stock (hospital_id, blood_type, units) VALUES (?, ?, ?)
                                   ON DUPLICATE KEY UPDATE units = VALUES(units)');
            foreach ($newUnits as $type => $units) {
                $stmt->execute([$hospitalId, $type, $units]);
            }
            setFlash('success', 'Stock updated.');
            redirect('hospital/dashboard.php');
        }

    // --- Approve or reject a blood request ---
    } elseif ($action === 'approve' || $action === 'reject') {
        $stmt = $pdo->prepare("SELECT * FROM blood_requests WHERE id = ? AND hospital_id = ? AND status = 'Pending'");
        $stmt->execute([(int)post('request_id'), $hospitalId]);
        $req = $stmt->fetch();

        if (!$req) {
            setFlash('error', 'Request not found or already handled.');
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE blood_requests SET status = 'Rejected' WHERE id = ?");
            $stmt->execute([$req['id']]);
            setFlash('success', 'Request rejected.');
        } else {
            // Approve: check the stock, then deduct it and update the request together
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT units FROM blood_stock WHERE hospital_id = ? AND blood_type = ? FOR UPDATE');
            $stmt->execute([$hospitalId, $req['blood_type']]);
            $available = (int)$stmt->fetchColumn();   // no row = 0 units

            if ($available < $req['units']) {
                $pdo->rollBack();
                setFlash('error', 'Not enough stock: only ' . $available . ' unit(s) of ' . $req['blood_type'] . ' available.');
            } else {
                $stmt = $pdo->prepare('UPDATE blood_stock SET units = units - ? WHERE hospital_id = ? AND blood_type = ?');
                $stmt->execute([$req['units'], $hospitalId, $req['blood_type']]);
                $stmt = $pdo->prepare("UPDATE blood_requests SET status = 'Approved' WHERE id = ?");
                $stmt->execute([$req['id']]);
                $pdo->commit();
                setFlash('success', 'Request approved and stock updated.');
            }
        }
        redirect('hospital/dashboard.php');

    // --- Confirm or decline a donation pledge ---
    } elseif ($action === 'confirm' || $action === 'decline') {
        $stmt = $pdo->prepare("SELECT p.id, u.blood_type FROM donation_pledges p
                               JOIN users u ON u.id = p.donor_id
                               WHERE p.id = ? AND p.hospital_id = ? AND p.status = 'Pending'");
        $stmt->execute([(int)post('pledge_id'), $hospitalId]);
        $pledge = $stmt->fetch();

        if (!$pledge) {
            setFlash('error', 'Pledge not found or already handled.');
        } elseif ($action === 'decline') {
            $stmt = $pdo->prepare("UPDATE donation_pledges SET status = 'Declined' WHERE id = ?");
            $stmt->execute([$pledge['id']]);
            setFlash('success', 'Pledge declined.');
        } else {
            // Confirm: mark the donation as done and add the units to stock together
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE donation_pledges SET status = 'Confirmed', donated_on = CURDATE() WHERE id = ?");
            $stmt->execute([$pledge['id']]);
            $stmt = $pdo->prepare('INSERT INTO blood_stock (hospital_id, blood_type, units) VALUES (?, ?, ?)
                                   ON DUPLICATE KEY UPDATE units = units + VALUES(units)');
            $stmt->execute([$hospitalId, $pledge['blood_type'], UNITS_PER_DONATION]);
            $pdo->commit();
            setFlash('success', 'Donation confirmed and stock updated.');
        }
        redirect('hospital/dashboard.php');
    }
}

// ===== Load the data shown on the page =====
$stmt = $pdo->prepare('SELECT blood_type, units FROM blood_stock WHERE hospital_id = ?');
$stmt->execute([$hospitalId]);
$stock = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);   // [blood type => units]

$stmt = $pdo->prepare("SELECT r.*, u.name AS patient_name FROM blood_requests r
                       JOIN users u ON u.id = r.patient_id
                       WHERE r.hospital_id = ?
                       ORDER BY r.status = 'Pending' DESC, r.id DESC");
$stmt->execute([$hospitalId]);
$requests = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.*, u.name AS donor_name, u.blood_type FROM donation_pledges p
                       JOIN users u ON u.id = p.donor_id
                       WHERE p.hospital_id = ?
                       ORDER BY p.status = 'Pending' DESC, p.id DESC");
$stmt->execute([$hospitalId]);
$pledges = $stmt->fetchAll();

$pageTitle = 'Hospital Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($_SESSION['name']) ?></h1>

<h2>Blood stock</h2>
<form method="post" action="dashboard.php">
    <input type="hidden" name="action" value="save_stock">
    <?php if ($errors): ?>
        <ul class="error-list"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <tr><th>Blood type</th><th>Status</th><th>Units (edit and save)</th></tr>
            <?php foreach ($types as $i => $type): ?>
                <tr>
                    <td><?= e($type) ?></td>
                    <td><?= isset($stock[$type]) ? levelBadge($stock[$type]) : '<span class="hint">not set</span>' ?></td>
                    <td><input type="number" name="units[<?= $i ?>]" min="0" max="9999" class="small-input"
                               value="<?= isset($stock[$type]) ? (int)$stock[$type] : '' ?>"></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <button type="submit" class="btn">Save stock</button>
    <p class="hint">LOW = below <?= STOCK_LIMITS['LOW'] ?> units, CRITICAL = below <?= STOCK_LIMITS['CRITICAL'] ?>. Leave a box empty to keep a type "not set".</p>
</form>

<h2>Incoming blood requests</h2>
<?php if (!$requests): ?>
    <p>No requests yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <tr><th>Date</th><th>Patient</th><th>Type</th><th>Units</th><th>Urgency</th><th>Note</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                    <td><?= e($r['patient_name']) ?></td>
                    <td><?= e($r['blood_type']) ?></td>
                    <td><?= (int)$r['units'] ?></td>
                    <td><?= e($r['urgency']) ?></td>
                    <td><?= e($r['note']) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td class="actions">
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="post" action="dashboard.php" data-confirm="Approve this request and deduct the stock?">
                                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-small">Approve</button>
                            </form>
                            <form method="post" action="dashboard.php" data-confirm="Reject this request?">
                                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-small btn-outline">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<h2>Incoming donation pledges</h2>
<?php if (!$pledges): ?>
    <p>No pledges yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <tr><th>Donor</th><th>Blood type</th><th>Pledge date</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($pledges as $p): ?>
                <tr>
                    <td><?= e($p['donor_name']) ?></td>
                    <td><?= e($p['blood_type']) ?></td>
                    <td><?= e($p['pledge_date']) ?></td>
                    <td><?= statusBadge($p['status']) ?></td>
                    <td class="actions">
                        <?php if ($p['status'] === 'Pending'): ?>
                            <form method="post" action="dashboard.php" data-confirm="Confirm that this donor has donated?">
                                <input type="hidden" name="pledge_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-small">Confirm</button>
                            </form>
                            <form method="post" action="dashboard.php" data-confirm="Decline this pledge?">
                                <input type="hidden" name="pledge_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn btn-small btn-outline">Decline</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
