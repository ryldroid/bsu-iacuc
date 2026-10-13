<?php foreach (UserModel::RESEARCHER_TYPES as $researcherTypeOption): ?>
  <option value="<?= $researcherTypeOption ?>" <?= ($selectedResearcherType ?? '') === $researcherTypeOption ? 'selected' : '' ?>><?= $researcherTypeOption ?></option>
<?php endforeach; ?>