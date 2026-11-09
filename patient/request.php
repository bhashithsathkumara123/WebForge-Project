<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

// All hospitals for the dropdown
$hospitals = $pdo->query("SELECT id, name FROM users WHERE role = 'hospital' ORDER BY name")->fetchAll();
$hospitalIds = array_column($hospitals, 'id');

$errors = [];
$form = ['hospital_id' => post('hospital_id'), 'blood_type' => post('blood_type'),
         'units' => post('units') ?: '1', 'urgency' => 'Normal', 'note' => ''];

// Handle the submitted request form
if (isset($_POST['send_request'])) {
    $form['urgency'] = post('urgency');
    $form['note'] = post('note');
    $units = filter_var($form['units'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);

    if (!in_array((int)$form['hospital_id'], $hospitalIds)) { $errors[] = 'Please choose a hospital.'; }
    if (!in_array($form['blood_type'], bloodTypes())) { $errors[] = 'Please choose a blood type.'; }
    if ($units === false) { $errors[] = 'Units must be a whole number from 1 to 20.'; }
    if (!in_array($form['urgency'], ['Normal', 'Urgent', 'Emergency'])) { $errors[] = 'Please choose an urgency level.'; }
    if (strlen($form['note']) > 255) { $errors[] = 'Note must be 255 characters or less.'; }

    // Save the request as Pending
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO blood_requests (patient_id, hospital_id, blood_type, units, urgency, note)
                               VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], (int)$form['hospital_id'], $form['blood_type'],
                        $units, $form['urgency'], $form['note']]);
        setFlash('success', 'Your request was sent to the hospital.');
        redirect('patient/dashboard.php');
    }
}

$pageTitle = 'Blood Request';
require __DIR__ . '/../includes/header.php';
?>
<h1>Submit a blood request</h1>
<form method="post" action="request.php" class="card form-narrow">
    <input type="hidden" name="send_request" value="1">
    <?php if ($errors): ?>
        <ul class="error-list"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div class="field">
        <label for="hospital_id">Hospital</label>
        <select id="hospital_id" name="hospital_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($hospitals as $h): ?>
                <option value="<?= (int)$h['id'] ?>" <?= (string)$h['id'] === (string)$form['hospital_id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="blood_type">Blood type to request</label>
        <select id="blood_type" name="blood_type" required><?= bloodTypeOptions($form['blood_type']) ?></select>
    </div>
    <div class="field">
        <label for="units">Units</label>
        <input type="number" id="units" name="units" min="1" max="20" value="<?= e($form['units']) ?>" required>
    </div>
    <div class="field">
        <label for="urgency">Urgency</label>
        <select id="urgency" name="urgency" required>
            <?php foreach (['Normal', 'Urgent', 'Emergency'] as $u): ?>
                <option value="<?= $u ?>" <?= $form['urgency'] === $u ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="note">Note (optional)</label>
        <textarea id="note" name="note" rows="3" maxlength="255"><?= e($form['note']) ?></textarea>
    </div>
    <button type="submit" class="btn">Send request</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
