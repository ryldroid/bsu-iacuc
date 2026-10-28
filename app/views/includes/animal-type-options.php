<datalist id="animal-type-options">
<?php foreach (RecordModel::ANIMAL_TYPES as $animalTypeOption): ?>
  <option value="<?= htmlspecialchars($animalTypeOption, ENT_QUOTES) ?>">
<?php endforeach; ?>
</datalist>