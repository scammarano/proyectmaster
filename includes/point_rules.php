<?php
// includes/point_rules.php
// Reglas por división / marca para construcción de puntos.
// La idea: centralizar la lógica aquí, NO regada por las vistas.
//
// Puedes extender esto por división: electrico, redes, cctv, etc.

function get_division_rules(int $division_id): array {
  // Lee de DB si existe tabla divisions con campos: id, name, prefix, rule_key
  // Fallback: por nombre/prefix.
  $div = db_query("SELECT * FROM divisions WHERE id=?", [$division_id])->fetch();
  if(!$div) return ['key'=>'generic'];

  $key = $div['rule_key'] ?? '';
  if(!$key){
    $nm = strtolower(trim($div['name'] ?? ''));
    if(strpos($nm,'elect')!==false) $key='electric_vimar';
    else $key='generic';
  }

  return [
    'key' => $key,
    'prefix' => $div['prefix'] ?? '',
    'name' => $div['name'] ?? '',
  ];
}

/**
 * Autonumeración por Área+División: PE01, PE02... reinicia en cada área.
 * Requiere area_division_counters.
 */
function next_point_code(int $area_id, int $division_id): string {
  $rules = get_division_rules($division_id);
  $prefix = strtoupper(trim($rules['prefix'] ?? ''));
  if($prefix==='') $prefix = 'P';

  db_query("INSERT IGNORE INTO area_division_counters(area_id,division_id,last_number) VALUES (?,?,0)", [$area_id,$division_id]);
  db_query("UPDATE area_division_counters SET last_number=last_number+1 WHERE area_id=? AND division_id=?", [$area_id,$division_id]);
  $n = (int)db_query("SELECT last_number FROM area_division_counters WHERE area_id=? AND division_id=?", [$area_id,$division_id])->fetch()['last_number'];

  return $prefix . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
}

/**
 * Valida un punto Vimar eléctrico:
 * - soporte obligatorio
 * - placa opcional
 * - suma(modules) de frutos <= modules del soporte
 * - si hay frutos con requires_cover=1, entonces debe haber cubretecla en el armado (luego)
 * Este validador devuelve ['ok'=>bool,'errors'=>[],'warnings'=>[],'fill_gap'=>int]
 */
function validate_vimar_point(array $payload): array {
  $errors=[]; $warnings=[];
  $support_modules = (int)($payload['support_modules'] ?? 0);
  $support_id = (int)($payload['support_article_id'] ?? 0);

  if($support_id<=0){
    $errors[] = 'Soporte es obligatorio para puntos eléctricos Vimar.';
  }
  if($support_modules<=0){
    $errors[] = 'El soporte no tiene módulos configurados.';
  }

  $fruits = $payload['fruits'] ?? []; // cada fruit: ['article_id'=>, 'modules'=>, 'requires_cover'=>0/1]
  $sum=0;
  $needs_cover=false;
  foreach($fruits as $f){
    $sum += (int)($f['modules'] ?? 0);
    if((int)($f['requires_cover'] ?? 0)===1) $needs_cover=true;
  }

  if($support_modules>0 && $sum>$support_modules){
    $errors[] = "La suma de módulos de frutos ($sum) supera los módulos del soporte ($support_modules).";
  }

  $gap = max(0, $support_modules - $sum);
  if($gap>0 && $support_id>0){
    $warnings[] = "Quedan $gap módulo(s) libres en el soporte.";
  }

  if($needs_cover){
    // por ahora warning; luego cuando tengamos cubreteclas por serie/color se vuelve validación fuerte.
    $warnings[] = 'Hay frutos que requieren cubretecla. Asegúrate de seleccionar cubreteclas compatibles.';
  }

  return ['ok'=>count($errors)===0,'errors'=>$errors,'warnings'=>$warnings,'fill_gap'=>$gap];
}

/**
 * Busca un fruto "módulo ciego" compatible con (brand_id, series_id, division_id) si existe.
 */
function find_blank_module_article_id(?int $brand_id, ?int $series_id, int $division_id): ?int {
  // Heurística: article_type='fruto', nombre o código contiene 'ciego' o 'blank'
  // y esté asociado a la división (article_divisions).
  $params = [$division_id];
  $where = "ad.division_id=?";
  if($brand_id){
    $where .= " AND a.brand_id=?";
    $params[] = $brand_id;
  }
  if($series_id){
    $where .= " AND a.series_id=?";
    $params[] = $series_id;
  }
  $sql = "
    SELECT a.id
    FROM articles a
    JOIN article_divisions ad ON ad.article_id=a.id
    WHERE $where
      AND a.article_type='fruto'
      AND (LOWER(a.name) LIKE '%ciego%' OR LOWER(a.name) LIKE '%blank%' OR LOWER(a.code) LIKE '%041%')
    ORDER BY a.id ASC
    LIMIT 1
  ";
  $row = db_query($sql,$params)->fetch();
  return $row ? (int)$row['id'] : null;
}

// ---------------------------------------------------------------------
// Compatibilidad: projects/detail.php espera point_rules_for_prefix($prefix)
// ---------------------------------------------------------------------
if (!function_exists('point_rules_for_prefix')) {
  function point_rules_for_prefix(string $prefix): array {
    $p = strtoupper(trim($prefix));

    // Ajusta aquí tus prefijos reales de divisiones
    // Ejemplo: 'PE' = Puntos Eléctricos (Vimar), 'PR' redes, 'PC' CCTV, etc.
    $electricalPrefixes = ['PE','EL','E','ELEC'];

    $isElectrical = in_array($p, $electricalPrefixes, true);

    return [
      'prefix' => $p,
      'mode' => $isElectrical ? 'electrical' : 'single', // electrical|single|composite
      'requires_support' => $isElectrical,              // usado en projects/detail.php
      // puedes extender luego:
      // 'requires_plate' => $isElectrical,
      // 'requires_box' => $isElectrical,
      // 'allow_orientation' => $isElectrical,
    ];
  }
}
