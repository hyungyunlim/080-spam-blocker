<?php
session_start();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(){
    if(!is_logged_in()){
        header('Location: login.php');
        exit;
    }
}

function current_user_phone(){
    return $_SESSION['phone'] ?? '';
}

// flash message helpers
function set_flash(string $msg){
    $_SESSION['flash']=$msg;
}
function get_flash(): ?string {
    if(isset($_SESSION['flash'])){
        $m=$_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}
?> 