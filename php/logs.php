<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/funcoes.php'; // Assumindo que 'registrar_log' está aqui
require_once '../includes/carregar_tema.php'; // Define $themeClass

// --- MODIFICAÇÃO 1: VERIFICAÇÃO DE ACESSO MELHORADA ---
// Apenas Admins podem acessar esta página
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    // Pega o ID da sessão se existir, senão, passa null
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    
    // Passa o ID (ou null) explicitamente para a função
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Logs de Atividades');
    
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}
// --- FIM DA MODIFICAÇÃO 1 ---


// --- ALTERAÇÃO 1: Mudar parâmetro de 'pagina' para 'page' ---
$pagina_selecionada = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
// --- FIM DA ALTERAÇÃO 1 ---

$registros_por_pagina = 20;
$offset = ($pagina_selecionada - 1) * $registros_por_pagina;

// Total de Registros (Original)
$total_sql = "SELECT COUNT(*) FROM logs";
$total_stmt = $pdo->query($total_sql);
$total_registros = $total_stmt->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Busca os Logs (Original)
$sql = "SELECT l.id, l.acao, l.detalhes, l.ip_usuario, l.data_ocorrencia, u.nome as nome_usuario
        FROM logs l
        LEFT JOIN usuarios u ON l.id_usuario = u.id
        ORDER BY l.data_ocorrencia DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Logs de Atividades</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.8rem; border: 1px solid #ddd; text-decoration: none; color: var(--cor-primaria); border-radius: 0.375rem; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination .active { background-color: var(--cor-primaria); color: white; border-color: var(--cor-primaria); }
        .pagination .disabled { color: #aaa; background-color: #f9f9f9; }
    </style>
    </head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Logs de Atividades do Sistema</h1>

            <div class="bg-white p-6 rounded-lg shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalhes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endereço IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="text-center py-4">Nenhum log encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['data_ocorrencia']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['nome_usuario'] ?? 'Sistema'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($log['acao']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo nl2br(htmlspecialchars($log['detalhes'] ?? 'N/A')); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['ip_usuario']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_selecionada > 1): ?>
                        <a href="?page=<?php echo $pagina_selecionada - 1; ?>">Anterior</a>
                    <?php endif; ?>

                    <?php 
                    $window = 1; // Lógica da página modelo
                    if ($pagina_selecionada > $window + 2) {
                        echo '<a href="?page=1">1</a>';
                        echo '<span class="disabled">...</span>';
                    }

                    for ($i = max(1, $pagina_selecionada - $window); $i <= min($total_paginas, $pagina_selecionada + $window); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $pagina_selecionada) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php
                    if ($pagina_selecionada < $total_paginas - $window - 1) {
                        echo '<span class="disabled">...</span>';
                        echo '<a href="?page='.$total_paginas.'"> '.$total_paginas.'</a>';
                    }
                    ?>

                    <?php if ($pagina_selecionada < $total_paginas): ?>
                        <a href="?page=<?php echo $pagina_selecionada + 1; ?>">Próxima</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                </div>
        </main>
    </div>
    
</body>
<script>
    const BASE_URL = 'http://localhost/juridico'; 
</script>
<script src="../js/script.js"></script>
</html>