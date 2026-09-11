<?php $editing = ($_POST['handover_action'] ?? '') === 'edit' && (int) ($_POST['register_id'] ?? 0) === (int) $row['id']; ?>
<details class="mt-2" <?= $editing ? 'open' : '' ?>>
    <summary class="btn btn-outline-primary btn-sm">Edit</summary>
    <form method="post" class="mt-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="handover_action" value="edit">
        <input type="hidden" name="register_id" value="<?= (int) $row['id'] ?>">
        <label class="form-label">Received amount (Rs.)
            <input class="form-control form-control-sm" type="number" name="accepted_amount" min="0" max="9999999999.99" step="0.01" required value="<?= e((string) ($editing ? ($_POST['accepted_amount'] ?? '') : $row['accepted_amount'])) ?>">
        </label>
        <label class="form-label">Note
            <input class="form-control form-control-sm" name="acceptance_note" maxlength="255" value="<?= e((string) ($editing ? ($_POST['acceptance_note'] ?? '') : ($row['acceptance_note'] ?? ''))) ?>">
        </label>
        <p class="small text-muted">Saving recalculates the balance and records you as the latest accepting admin.</p>
        <button class="btn btn-primary btn-sm" type="submit">Save Changes</button>
    </form>
</details>
