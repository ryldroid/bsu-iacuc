<datalist id="school-options">
<?php foreach (UserModel::SCHOOLS as $schoolOption): ?>
  <option value="<?= htmlspecialchars($schoolOption, ENT_QUOTES) ?>">
<?php endforeach; ?>
</datalist>