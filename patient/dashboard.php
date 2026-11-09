<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');
$patientId = $_SESSION['user_id'];

$error = '';
$exact = [];
$compatible = [];
$searched = isset($_POST['search']);
$searchType = post('blood_type') ?: $_SESSION['blood_type'];
$searchUnits = post('units') ?: '1';

// Show one results table; each row has a button that opens the request form
function showStockTable($rows, $units) {
    echo '<div class="table-wrap"><table>';
    echo '<tr><th>Hospital</th><th>Blood type</th><th>Units available</th><th></th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . e($row['hospital_name']) . '</td>';
        echo '<td>' . e($row['blood_type']) . '</td>';
        echo '<td>' . (int)$row['units'] . '</td>';
        echo '<td><form method="post" action="request.php">';
        echo '<input type="hidden" name="hospital_id" value="' . (int)$row['hospital_id'] . '">';
        echo '<input type="hidden" name="blood_type" value="' . e($row['blood_type']) . '">';
        echo '<input type="hidden" name="units" value="' . (int)$units . '">';
        echo '<button type="submit" class="btn btn-small">Request this blood</button>';
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</table></div>';
}

// Handle the search form
if ($searched) {
    $units = filter_var($searchUnits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if (!in_array($searchType, bloodTypes())) {
        $error = 'Please choose a valid blood type.';
    } elseif ($units === false) {
        $error = 'Units must be a whole number from 1 to 20.';
    } else {
        // All stock of compatible blood types with enough units
        $types = compatibleTypes($searchType);
        $marks = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT s.hospital_id, s.blood_type, s.units, u.name AS hospital_name
                FROM blood_stock s
                JOIN users u ON u.id = s.hospital_id
                WHERE s.blood_type IN ($marks) AND s.units >= ?
                ORDER BY s.units DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($types, [$units]));

        // Exact matches first, then the other compatible types
        foreach ($stmt->fetchAll() as $row) {
            if ($row['blood_type'] === $searchType) {
                $exact[] = $row;
            } else {
                $compatible[] = $row;
            }
        }
    }
}

// This patient's requests, newest first
$stmt = $pdo->prepare('SELECT r.*, u.name AS hospital_name FROM blood_requests r
                       JOIN users u ON u.id = r.hospital_id
                       WHERE r.patient_id = ? ORDER BY r.id DESC');
$stmt->execute([$patientId]);
$requests = $stmt->fetchAll();

$pageTitle = 'Patient Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($_SESSION['name']) ?></h1>

<h2>Search for blood</h2>
<form method="post" action="dashboard.php" class="card form-inline">
    <input type="hidden" name="search" value="1">
    <div class="field">
        <label for="blood_type">Blood type needed</label>
        <select id="blood_type" name="blood_type" required><?= bloodTypeOptions($searchType) ?></select>
    </div>
    <div class="field">
        <label for="units">Units required</label>
        <input type="number" id="units" name="units" min="1" max="20" value="<?= e($searchUnits) ?>" required>
    </div>
    <button type="submit" class="btn">Search blood</button>
</form>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php elseif ($searched): ?>
    <h3>Exact match: <?= e($searchType) ?></h3>
    <?php if ($exact) { showStockTable($exact, $units); } else { echo '<p>No hospital has enough ' . e($searchType) . ' blood.</p>'; } ?>

    <h3>Compatible blood types</h3>
    <?php if ($compatible) { showStockTable($compatible, $units); } else { echo '<p>No compatible blood found.</p>'; } ?>
<?php endif; ?>

<h2>My requests</h2>
<?php if (!$requests): ?>
    <p>You have not made any requests yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <tr><th>Date</th><th>Hospital</th><th>Type</th><th>Units</th><th>Urgency</th><th>Note</th><th>Status</th></tr>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                    <td><?= e($r['hospital_name']) ?></td>
                    <td><?= e($r['blood_type']) ?></td>
                    <td><?= (int)$r['units'] ?></td>
                    <td><?= e($r['urgency']) ?></td>
                    <td><?= e($r['note']) ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
