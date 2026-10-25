<?php

// ===== ORDERABLE CONTENT MODEL =====
// Base for site-content models that can be fetched by id and reordered.
abstract class OrderableModel extends Model
{
  abstract public function getById(int $id): ?array;

  abstract public function move(int $id, string $direction): bool;
}
