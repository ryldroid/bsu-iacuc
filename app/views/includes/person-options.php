<datalist id="adviser-options">
  <?php foreach ($advisers ?? [] as $adviserName): ?>
    <option value="<?= htmlspecialchars($adviserName) ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="veterinarian-options">
  <?php foreach ($veterinarians ?? [] as $veterinarianName): ?>
    <option value="<?= htmlspecialchars($veterinarianName) ?>">
    <?php endforeach; ?>
</datalist>