<?php
// views/points/create.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/point_rules.php';

require_login();

$area_id = (int)get_param('area_id',0);
$area = db_query("SELECT * FROM areas WHERE id=?",[$area_id])->fetch();
if(!$area) die('Área no encontrada');

$project = db_query("SELECT * FROM projects WHERE id=?",[(int)$area['project_id']])->fetch();
$project_id = (int)$project['id'];

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;
if($closed) die('Proyecto cerrado');

if(is_post()){
  $division_id = (int)post_param('division_id',0);
  $name = trim(post_param('name',''));
  $location = trim(post_param('location',''));
  $support_article_id = (int)post_param('support_article_id',0);
  $plate_article_id = (int)post_param('plate_article_id',0);
  $cover_article_id = (int)post_param('cover_article_id',0);

  $fruits_json = post_param('fruits_json','[]');
  $fruits = json_decode($fruits_json,true);
  if(!is_array($fruits)) $fruits=[];

  if($division_id<=0){
    set_flash('error','Selecciona una división.');
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  // Soporte modules lookup
  $support_modules = 0;
  $brand_id = null;
  $series_id = $area['default_series_id'] ?? null;

  if($support_article_id>0){
    $s = db_query("SELECT id,modules,brand_id,series_id FROM articles WHERE id=?",[$support_article_id])->fetch();
    if($s){
      $support_modules = (int)($s['modules'] ?? 0);
      $brand_id = $s['brand_id'] ?? null;
      if(!$series_id) $series_id = $s['series_id'] ?? null;
    }
  }

  // Build payload for validator
  $payload = [
    'support_article_id'=>$support_article_id,
    'support_modules'=>$support_modules,
    'fruits'=>[]
  ];
  foreach($fruits as $f){
    $payload['fruits'][] = [
      'article_id'=>(int)($f['article_id']??0),
      'modules'=>(int)($f['modules']??0),
      'requires_cover'=>(int)($f['requires_cover']??0),
    ];
  }

  $rules = get_division_rules($division_id);

  // Si es eléctrico Vimar, valida fuerte. (por ahora: si rule key coincide)
  if(($rules['key'] ?? '')==='electric_vimar'){
    $val = validate_vimar_point($payload);
    if(!$val['ok']){
      foreach($val['errors'] as $e) set_flash('error',$e);
      foreach($val['warnings'] as $w) set_flash('warning',$w);
      redirect('index.php?page=point_create&area_id='.$area_id);
    } else {
      foreach($val['warnings'] as $w) set_flash('warning',$w);
    }

    // Si el usuario aprobó "rellenar ciegos" (placeholder article_id=0), completamos con un artículo real.
    foreach($payload['fruits'] as $ix=>$f){
      if((int)$f['article_id']===0 && (int)$f['modules']>0){
        $blank_id = find_blank_module_article_id($brand_id ? (int)$brand_id : null, $series_id ? (int)$series_id : null, $division_id);
        if($blank_id){
          $payload['fruits'][$ix]['article_id'] = $blank_id;
          // modules del blank normalmente 1; si gap>1, insertaremos varios rows luego
        } else {
          set_flash('warning','No se encontró módulo ciego compatible para completar automáticamente.');
        }
      }
    }
  }

  // Generar código por área+división
  $code = next_point_code($area_id,$division_id);

  // Insert punto base (point_code, location)
  db_query("INSERT INTO points(project_id,area_id,division_id,point_code,name,location,support_article_id,plate_article_id,cover_article_id,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())",
    [$project_id,$area_id,$division_id,$code,$name,$location,
     ($support_article_id?:null), ($plate_article_id?:null), ($cover_article_id?:null)]
  );
  $point_id = (int)db_connect()->lastInsertId();

  // Guardar componentes frutos en point_components (si existe)
  try{
    foreach($payload['fruits'] as $f){
      $aid = (int)$f['article_id'];
      $mods = (int)$f['modules'];
      if($aid<=0 || $mods<=0) continue;

      // Si el blank fue usado para llenar gap>1, replicamos por módulos si el artículo es 1M
      $am = (int)(db_query("SELECT modules FROM articles WHERE id=?",[$aid])->fetch()['modules'] ?? 1);
      if($am<=0) $am=1;

      if($mods> $am && $am===1){
        for($i=0;$i<$mods;$i++){
          db_query("INSERT INTO point_components(point_id,article_id,quantity) VALUES (?,?,1)", [$point_id,$aid]);
        }
      } else {
        $qty = max(1, (int)ceil($mods / $am));
        db_query("INSERT INTO point_components(point_id,article_id,quantity) VALUES (?,?,?)", [$point_id,$aid,$qty]);
      }
    }
  }catch(Throwable $e){
    // si la tabla no existe todavía, lo afinamos cuando integremos puntos a full.
  }

  if(function_exists('user_log')) user_log('point_create','point',$point_id,"$code $name");

  set_flash('success',"Punto $code creado.");
  redirect('index.php?page=area_detail&id='.$area_id);
}

include __DIR__ . '/../layout/header.php';
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h4 mb-0">Nuevo punto</h1>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=area_detail&id=<?=h($area_id)?>"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <form method="post">
    <?php
      $mode='create';
      $context='area';
      $point=[];
      include __DIR__.'/form.php';
    ?>
    <div class="mt-3 d-grid">
      <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar punto</button>
    </div>
  </form>

  <div class="text-muted small mt-2">
    Nota: en la siguiente iteración integraremos selección de plantilla y edición completa del punto.
  </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
