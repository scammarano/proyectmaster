<?php
// Cargador central de reglas (archivo editable /config/rules.php)
function load_rules(): array {
  static $rules = null;
  if ($rules !== null) return $rules;
  $path = __DIR__ . '/../config/rules.php';
  if (!file_exists($path)) return $rules = [];
  $r = require $path;
  return $rules = (is_array($r) ? $r : []);
}

function rules_articles_type_field_rules(): array {
  $rules = load_rules();
  return $rules['articles']['type_field_rules'] ?? [];
}

function rules_articles_default(): array {
  $rules = load_rules();
  return $rules['articles']['default'] ?? ['show'=>[],'hide'=>[]];
}
