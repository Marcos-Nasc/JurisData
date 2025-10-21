<?php
// 1. Inicia a sessão para poder LER os dados dela.
session_start();

// --- INÍCIO DO REGISTRO DE LOG ---

// 2. Incluir os arquivos de conexão e funções
// (Ajuste o caminho para 'includes' se o seu logout.php estiver em outra pasta)
require_once '../includes/conexao.php';
require_once '../includes/funcoes.php'; 

// 3. Capturar os dados do usuário ANTES de destruir a sessão
$id_usuario = $_SESSION['user_id'] ?? null;
$nome_usuario = $_SESSION['nome_usuario'] ?? 'Usuário desconhecido';

// 4. Registrar o log (Apenas se um usuário estava realmente logado)
if ($id_usuario) {
    $detalhes = "Usuário '" . htmlspecialchars($nome_usuario) . "' (ID: " . $id_usuario . ") saiu do sistema.";
    registrar_log($pdo, "Logout do Sistema", $id_usuario, $detalhes);
}
// --- FIM DO REGISTRO DE LOG ---


// 5. Limpa todas as variáveis da sessão.
$_SESSION = array();

// 6. Destrói a sessão.
session_destroy();

// 7. Redireciona para a página de login (usando o caminho que você já tinha).
header('Location: ../php/login.php?status=logout_sucesso');
exit();
?>