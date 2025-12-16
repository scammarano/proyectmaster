<?php
// views/points/form.php
// Form reutilizable para:
// - crear punto desde área
// - crear punto desde plantilla (en otra iteración)
// Requiere variables: $mode ('create'|'edit'), $context ('area'|'template'), $area, $project, $point (opcional)
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/point_rules.php';

$mode = $mode ?? 'create';
$context = $context ?? 'area';

$point = $point ?? [];
$area_id = (int)($area['id'] ?? 0);
$project_id = (int)($project['id'] ?? 0);

// divisiones del proyecto (si tienes project_divisions, ajusta)
$divisions = db_query("
  SELECT d.*
  FROM divisions d
  JOIN project_divisions pd ON pd.division_id=d.id
  WHERE pd.project_id=?
  ORDER BY d.name
", [$project_id])->fetchAll();

$selected_division_id = (int)($point['division_id'] ?? 0);
$selected_series_id = (int)($area['default_series_id'] ?? 0);

// Filtros de artículos por división + (opcional) serie del área
function articles_for_select(int $division_id, string $type, ?int $series_id=null): array {
  $params = [$division_id, $type];
  $where = "ad.division_id=? AND a.article_type=?";
  if($series_id){
    $where .= " AND (a.series_id=? OR a.series_id IS NULL)";
    $params[] = $series_id;
  }
  $sql = "
    SELECT a.id, a.code, a.name, a.modules, a.requires_cover
    FROM articles a
    JOIN article_divisions ad ON ad.article_id=a.id
    WHERE $where
    ORDER BY a.name
  ";
  return db_query($sql,$params)->fetchAll();
}

$supports = $selected_division_id ? articles_for_select($selected_division_id,'soporte',$selected_series_id?:null) : [];
$plates   = $selected_division_id ? articles_for_select($selected_division_id,'placa',$selected_series_id?:null) : [];
$fruits   = $selected_division_id ? articles_for_select($selected_division_id,'fruto',$selected_series_id?:null) : [];
$covers   = $selected_division_id ? articles_for_select($selected_division_id,'cubretecla',$selected_series_id?:null) : [];
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-diagram-3"></i> <?= $mode==='create'?'Crear punto':'Editar punto' ?></span>
    <span class="text-muted small">Área: <?=h($area['name'] ?? '')?></span>
  </div>

  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">División (sistema)</label>
        <select class="form-select" name="division_id" id="division_id" required>
          <option value="">(selecciona)</option>
          <?php foreach($divisions as $d): ?>
            <option value="<?=h($d['id'])?>" <?=((int)$d['id']===$selected_division_id?'selected':'')?>>
              <?=h($d['name'])?> <?= $d['prefix']?('('.h($d['prefix']).')'):'' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Filtra artículos por división (y reglas por marca).</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Ubicación dentro del área</label>
        <input class="form-control" name="location" value="<?=h($point['location'] ?? '')?>" placeholder="Ej: Entrada, Mesita Izq, Techo">
      </div>

      <div class="col-md-6">
        <label class="form-label">Nombre / descripción</label>
        <input class="form-control" name="name" value="<?=h($point['name'] ?? '')?>" placeholder="Ej: Interruptor principal + 3way">
      </div>
    </div>

    <hr class="my-3">

    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Soporte (obligatorio en Vimar eléctrico)</label>
        <select class="form-select" name="support_article_id" id="support_article_id">
          <option value="">(sin soporte)</option>
          <?php foreach($supports as $a): ?>
            <option value="<?=h($a['id'])?>" data-mod="<?=h($a['modules'] ?? 0)?>">
              <?=h($a['name'])?> <?=h($a['code'])?> — <?=h($a['modules'])?>M
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Placa (opcional)</label>
        <select class="form-select" name="plate_article_id" id="plate_article_id">
          <option value="">(sin placa)</option>
          <?php foreach($plates as $a): ?>
            <option value="<?=h($a['id'])?>"><?=h($a['name'])?> <?=h($a['code'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">Frutos / mecanismos</label>
      <div class="row g-2">
        <div class="col-md-7">
          <select class="form-select" id="fruit_pick">
            <option value="">(selecciona un fruto)</option>
            <?php foreach($fruits as $a): ?>
              <option value="<?=h($a['id'])?>" data-mod="<?=h($a['modules'] ?? 1)?>" data-cover="<?=h($a['requires_cover'] ?? 0)?>">
                <?=h($a['name'])?> <?=h($a['code'])?> — <?=h($a['modules'])?>M<?= ((int)($a['requires_cover']??0)===1?' • cubretecla':'') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="button" class="btn btn-outline-primary" onclick="addFruit()"><i class="bi bi-plus-circle"></i> Agregar</button>
        </div>
        <div class="col-md-3 d-grid">
          <button type="button" class="btn btn-outline-secondary" onclick="suggestFill()"><i class="bi bi-magic"></i> Completar ciegos</button>
        </div>
      </div>

      <div class="table-responsive mt-2">
        <table class="table table-sm table-striped align-middle" id="fruits_table">
          <thead><tr><th>Fruto</th><th class="text-end">Módulos</th><th>Cubretecla</th><th class="text-end">Acción</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="alert alert-info small" id="mod_info" style="display:none"></div>

      <div class="mt-2">
        <label class="form-label">Cubretecla (si aplica)</label>
        <select class="form-select" name="cover_article_id" id="cover_article_id">
          <option value="">(sin cubretecla)</option>
          <?php foreach($covers as $a): ?>
            <option value="<?=h($a['id'])?>"><?=h($a['name'])?> <?=h($a['code'])?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">En Vimar, se muestra si hay frutos que requieren cubretecla.</div>
      </div>
    </div>

  </div>
</div>

<input type="hidden" name="fruits_json" id="fruits_json" value="[]">

<script>
let fruits = [];

function renderFruits(){
  const tb = document.querySelector('#fruits_table tbody');
  tb.innerHTML = '';
  let sum = 0, needsCover=false;
  fruits.forEach((f,idx)=>{
    sum += parseInt(f.modules||0,10);
    if(parseInt(f.requires_cover||0,10)===1) needsCover=true;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(f.label)}</td>
      <td class="text-end">${f.modules}</td>
      <td>${parseInt(f.requires_cover,10)===1?'Sí':'No'}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFruit(${idx})"><i class="bi bi-trash"></i></button>
      </td>`;
    tb.appendChild(tr);
  });

  document.getElementById('fruits_json').value = JSON.stringify(fruits);

  const sup = document.getElementById('support_article_id');
  const supOpt = sup.options[sup.selectedIndex];
  const supMod = parseInt((supOpt && supOpt.dataset.mod) ? supOpt.dataset.mod : '0', 10);

  const info = document.getElementById('mod_info');
  if(supMod>0){
    info.style.display = '';
    info.innerHTML = `<b>Módulos:</b> soporte ${supMod}M • frutos ${sum}M • libres ${Math.max(0, supMod-sum)}M` + (sum>supMod ? ' <span class="text-danger fw-bold">(excede)</span>' : '');
  }else{
    info.style.display = 'none';
  }

  // Cubres: mostrar/hint
  const coverSel = document.getElementById('cover_article_id');
  if(needsCover){
    coverSel.closest('.mt-2').style.opacity = '1';
  }else{
    // no lo oculto para no confundir, pero lo atenuo
    coverSel.closest('.mt-2').style.opacity = '.65';
  }
}

function addFruit(){
  const sel = document.getElementById('fruit_pick');
  const opt = sel.options[sel.selectedIndex];
  if(!opt || !opt.value) return;
  fruits.push({
    article_id: parseInt(opt.value,10),
    modules: parseInt(opt.dataset.mod || '1',10),
    requires_cover: parseInt(opt.dataset.cover || '0',10),
    label: opt.text
  });
  sel.value = '';
  renderFruits();
}

function removeFruit(i){
  fruits.splice(i,1);
  renderFruits();
}

function suggestFill(){
  const sup = document.getElementById('support_article_id');
  const opt = sup.options[sup.selectedIndex];
  const supMod = parseInt((opt && opt.dataset.mod) ? opt.dataset.mod : '0', 10);
  if(supMod<=0){ alert('Selecciona un soporte primero.'); return; }
  let sum = fruits.reduce((a,f)=>a+parseInt(f.modules||0,10),0);
  const gap = supMod - sum;
  if(gap<=0){ alert('No hay espacios por completar.'); return; }
  if(!confirm('Quedan ' + gap + ' módulo(s) libres. ¿Deseas rellenar con módulos ciegos?')) return;
  // En esta iteración no insertamos automáticamente el artículo ciego (requiere lookup en backend).
  // Dejamos un placeholder para que el backend complete en save si el usuario lo aprobó.
  fruits.push({article_id: 0, modules: gap, requires_cover: 0, label: '[Rellenar con ciegos: '+gap+'M]'});
  renderFruits();
}

function escapeHtml(s){
  return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

document.getElementById('support_article_id').addEventListener('change', renderFruits);

renderFruits();
</script>
