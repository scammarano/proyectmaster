
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
function auth_login($username, $password) {
    $user = db_query("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username])->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
        return true;
    }
    return false;
}
function auth_logout() {
    unset($_SESSION['user']);
}
