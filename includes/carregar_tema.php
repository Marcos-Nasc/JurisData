<?php
/**
 * CARREGADOR DE TEMA DO USUÁRIO
 *
 * Este script verifica a preferência de tema do usuário logado
 * e define a variável $themeClass.
 *
 * Depende de:
 * - Uma sessão já iniciada (session_start())
 * - A variável de conexão $pdo
 * - A variável de sessão $_SESSION['user_id']
 */

$themeClass = ''; // Define o padrão (light)

// 1. Verifica se o usuário está logado
if (isset($_SESSION['user_id'])) {
    try {
        // 2. Busca a preferência DELE no banco
        // Usamos um alias 'u' para a tabela usuarios
        $sql = "SELECT u.preferencia_tema FROM usuarios u WHERE u.id = :id_usuario";
        $stmt_tema = $pdo->prepare($sql); // Use um nome de var diferente (ex: $stmt_tema)
        $stmt_tema->bindParam(':id_usuario', $_SESSION['user_id']);
        $stmt_tema->execute();
        
        $preferencia = $stmt_tema->fetchColumn();

        // 3. Define a classe
        if ($preferencia == 'dark') {
            $themeClass = 'dark';
        }

    } catch (PDOException $e) {
        // Se der erro no banco, apenas carrega o tema claro
        // (Você pode querer registrar $e->getMessage() em um log)
        // O $themeClass continuará como '' (light)
    }
}
?>