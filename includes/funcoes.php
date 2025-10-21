<?php
/**
 * Registra uma ação do usuário no log do sistema.
 *
 * Esta função agora é flexível e aceita o ID do usuário como parâmetro,
 * permitindo o registro de logs antes mesmo da sessão ser iniciada (ex: falha de login).
 *
 * @param PDO $pdo A instância da conexão PDO.
 * @param string $acao A descrição da ação (ex: "Falha no Login").
 * @param int|null $id_usuario O ID do usuário que executou a ação (null se for desconhecido ou não aplicável).
 * @param string|null $detalhes Detalhes adicionais sobre a ação.
 */
function registrar_log($pdo, $acao, $id_usuario = null, $detalhes = null) {
    try {
        // Não precisamos mais checar a sessão aqui, 
        // mas é bom garantir que o IP seja pego.
        $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

        $sql = "INSERT INTO logs (id_usuario, acao, detalhes, ip_usuario) 
                VALUES (:id_usuario, :acao, :detalhes, :ip_usuario)";
        
        $stmt = $pdo->prepare($sql);

        // --- CORREÇÃO IMPORTANTE ---
        // Precisamos tratar valores NULL de forma especial com bindParam.
        // Se passarmos PDO::PARAM_INT para um $id_usuario que é null, 
        // o banco pode salvar 0 em vez de NULL.

        $stmt->bindParam(':id_usuario', $id_usuario, $id_usuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':acao', $acao, PDO::PARAM_STR);
        $stmt->bindParam(':detalhes', $detalhes, $detalhes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':ip_usuario', $ip_usuario, PDO::PARAM_STR);

        $stmt->execute();
    } catch (PDOException $e) {
        // Em produção, é ideal logar isso em um arquivo de texto.
        error_log("Erro ao registrar log no BD: " . $e->getMessage());
    }
}
?>