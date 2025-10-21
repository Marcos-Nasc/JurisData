<?php
/**
 * Guardião de Autenticação.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- VERIFICAÇÃO DO MODO DE MANUTENÇÃO ---
$maintenance_flag_path = __DIR__ . '/../maintenance.flag';
if (file_exists($maintenance_flag_path)) {
    if ((!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        http_response_code(503); // Service Unavailable
        echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Sistema em Manutenção</title><style>body{font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; text-align: center;} .container{padding: 40px; border-radius: 8px; background-color: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.1);}.icon{font-size: 50px; color: #ff9800; margin-bottom: 20px;}</style></head><body><div class="container"><div class="icon">&#9881;</div><h1>Sistema em Manutenção</h1><p>Estamos realizando atualizações no momento. O sistema estará disponível novamente em breve.</p><p>Agradecemos a sua paciência.</p></div></body></html>';
        exit();
    }
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    
    // CAPTURA A URL QUE O USUÁRIO TENTAVA ACESSAR
    // $_SERVER['REQUEST_URI'] contém o caminho e os parâmetros, ex: /processo.php?id=123
    $redirect_url = urlencode($_SERVER['REQUEST_URI']);

    session_destroy();
    
    // REDIRECIONA PARA O LOGIN, PASSANDO A URL DE DESTINO
    header('Location: ../php/login.php?erro=acesso_restrito&redirect_url=' . $redirect_url);
    
    exit();
}

// Se chegou aqui, o usuário está logado.

define('BASE_URL', 'http://localhost/juridico'); // MUDE 'seu_projeto' SE NECESSÁRIO

// --- Credenciais do Banco de Dados ---
define('DB_HOST', 'localhost');

// Carrega as configurações globais da aplicação
require_once __DIR__ . '/config.php';
?>