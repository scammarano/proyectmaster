<?php
// includes/rules.php
// Cargador central de reglas (archivo editable /config/rules.php)
// Compatible: si ya existe este archivo en tu proyecto, reemplázalo por este (mantiene funciones anteriores).

function load_rules(): array {
  static $rules = null;
  if ($rules !== null) return $rules;
  $path = __DIR__ . '/../config/rules.php';
  if (!file_exists($path)) return $rules = [];
  $r = require $path;
  return $rules = (is_array($r) ? $r : []);
}

/** =========================
 *  REGLAS DE ARTÍCULOS
 *  ========================= */
function rules_articles_type_field_rules(): array {
  $rules = load_rules();
  return $rules['articles']['type_field_rules'] ?? [];
}

function rules_articles_default(): array {
  $rules = load_rules();
  return $rules['articles']['default'] ?? ['show'=>[],'hide'=>[]];
}

/** =========================
 *  REGLAS DE PUNTOS
 *  Fuente: /config/rules.php generado por el wizard
 *  Estructura esperada:
 *  return [
 *    'points' => [
 *      'division_rules' => [
 *         'ELECTRICO' => [ ... ],  // por división code o nombre normalizado
 *         1 => [ ... ],            // o por division_id
 *      ],
 *      'default' => [ ... ]
 *    ],
 *    ...
 *  ];
 *  ========================= */
function rules_points_default(): array {
  $rules = load_rules();
  return $rules['points']['default'] ?? [];
}

function rules_points_for_division($division_key): array {
  $rules = load_rules();
  $map = $rules['points']['division_rules'] ?? [];
  if (isset($map[$division_key])) return $map[$division_key];
  // soporte para key normalizada (string)
  if (is_string($division_key)) {
    $k = strtoupper(trim($division_key));
    if (isset($map[$k])) return $map[$k];
  }
  return rules_points_default();
}
