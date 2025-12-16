
<?php include __DIR__.'/../layout/header.php';?>
<div class="row justify-content-center">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-header text-center"><strong>Iniciar sesión</strong></div>
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Usuario</label>
            <input type="text" name="username" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
          </div>
        </form>
        <hr>
        <p class="small text-muted mb-0">
          Si es la primera vez, ejecuta <code>reset_admin.php</code> para crear el usuario <strong>admin / admin123</strong>.
        </p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/../layout/footer.php';?>
