
<?php
require __DIR__.'/includes/db.php';
require __DIR__.'/includes/functions.php';
$pass='admin123'; $hash=password_hash($pass,PASSWORD_BCRYPT);
$u=db_query("SELECT id FROM users WHERE username='admin'")->fetch();
if($u){
  db_query("UPDATE users SET password_hash=?,role='admin',is_active=1 WHERE id=?",[$hash,$u['id']]);
  echo "Contraseña de 'admin' actualizada a '$pass'";
}else{
  db_query("INSERT INTO users (username,password_hash,role,is_active) VALUES (?,?,?,1)",['admin',$hash,'admin']);
  echo "Usuario 'admin' creado con contraseña '$pass'";
}
