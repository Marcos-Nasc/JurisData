<?php
// Inicia a sessão e inclui arquivos essenciais
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass
// --- LOG: INCLUSÃO DE DEPENDÊNCIAS ---
require_once '../includes/funcoes.php';
// --- FIM DO LOG ---

// --- LOG 1: VERIFICAÇÃO DE NÍVEL DE ACESSO ---
// Esta página permite manipular dados de processos. Deve ser restrita a Editores (2) ou Admins (3).
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] < 2) { 
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Processos Detalhados');
    die('Acesso negado. Você não tem permissão para acessar esta página.');
}
// --- FIM DO LOG 1 ---

// ID do usuário logado para ações de GET (como exportar)
$user_id_logado = $_SESSION['user_id'];

// --- FILTROS E PARÂMETROS --- 
$ano_selecionado = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$busca = trim($_GET['busca'] ?? '');

// LÓGICA DE EXPORTAÇÃO CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    
    // --- LOG 2: EXPORTAÇÃO CSV ---
    $log_detail = "Exportou CSV de Processos Detalhados.";
    if ($ano_selecionado) $log_detail .= " Ano: $ano_selecionado.";
    if (!empty($busca)) $log_detail .= " Busca: '$busca'.";
    registrar_log($pdo, 'Exportação CSV (Processos)', $user_id_logado, $log_detail);
    // --- FIM DO LOG 2 ---

    try {
        // ... [Seu código de exportação original] ...
        $where_conditions_export = [];
        $params_export = [];
        if ($ano_selecionado) {
            $where_conditions_export[] = "ano = :ano";
            $params_export[':ano'] = $ano_selecionado;
        }
        if (!empty($busca)) {
            $where_conditions_export[] = "(numero_processo LIKE :busca OR autor LIKE :busca OR materia LIKE :busca)";
            $params_export[':busca'] = '%' . $busca . '%';
        }
        $where_sql_export = count($where_conditions_export) > 0 ? ' WHERE ' . implode(' AND ', $where_conditions_export) : '';

        $sql_export = "SELECT * FROM processos_judiciais" . $where_sql_export . " ORDER BY data_recebimento DESC, id DESC";
        $stmt_export = $pdo->prepare($sql_export);
        $stmt_export->execute($params_export);
        $registros_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=relatorio_processos_detalhados.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($registros_export[0] ?? []));
        foreach ($registros_export as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();

    } catch (PDOException $e) {
        die("Erro ao gerar o CSV: " . $e->getMessage());
    }
}

// PARTE 1: PROCESSAMENTO DE AÇÕES CRUD (VIA AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Ação desconhecida.'];
    $user_id = $_SESSION['user_id'] ?? null; // ID para ações de POST

    if ($_POST['action'] === 'salvar_processo' && $user_id) {
        $id = filter_input(INPUT_POST, 'processo_id', FILTER_VALIDATE_INT);
        $numero_processo = trim($_POST['numero_processo'] ?? '');
        $autor = trim($_POST['autor'] ?? '');
        $data_recebimento = trim($_POST['data_recebimento'] ?? '');
        $ano = filter_input(INPUT_POST, 'ano', FILTER_VALIDATE_INT);
        $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
        // ... [restante das suas variáveis] ...
        $materia = trim($_POST['materia'] ?? null);
        $central_custo = trim($_POST['central_custo'] ?? null);
        $sentenca_1_instancia = trim($_POST['sentenca_1_instancia'] ?? null);
        $recurso = trim($_POST['recurso'] ?? null);
        
        function clean_decimal($value) { return empty($value) ? 0.00 : floatval(str_replace(',', '.', $value)); }
        $valor_causa = clean_decimal($_POST['valor_causa']);
        $despesas_processuais_1 = clean_decimal($_POST['despesas_processuais_1']);
        $despesas_processuais_2 = clean_decimal($_POST['despesas_processuais_2']);
        $valor_pago = clean_decimal($_POST['valor_pago']);
        $economia = clean_decimal($_POST['economia']);

        if (!empty($numero_processo) && !empty($autor) && !empty($data_recebimento)) {
            try {
                if ($id) { // UPDATE
                    $sql = "UPDATE processos_judiciais SET numero_processo=?, autor=?, data_recebimento=?, ano=?, mes=?, materia=?, central_custo=?, valor_causa=?, sentenca_1_instancia=?, recurso=?, despesas_processuais_1=?, despesas_processuais_2=?, valor_pago=?, economia=? WHERE id=?";
                    $params = [$numero_processo, $autor, $data_recebimento, $ano, $mes, $materia, $central_custo, $valor_causa, $sentenca_1_instancia, $recurso, $despesas_processuais_1, $despesas_processuais_2, $valor_pago, $economia, $id];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $response = ['status' => 'success', 'message' => 'Processo atualizado com sucesso!'];

                    // --- LOG 3: EDIÇÃO DE PROCESSO (SUCESSO) ---
                    $detalhes = "Atualizou o processo: '$numero_processo' (ID: $id).";
                    registrar_log($pdo, 'Edição de Processo (Sucesso)', $user_id, $detalhes);
                    // --- FIM DO LOG 3 ---

                } else { // INSERT
                    $sql = "INSERT INTO processos_judiciais (numero_processo, autor, data_recebimento, ano, mes, materia, central_custo, valor_causa, sentenca_1_instancia, recurso, despesas_processuais_1, despesas_processuais_2, valor_pago, economia, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [$numero_processo, $autor, $data_recebimento, $ano, $mes, $materia, $central_custo, $valor_causa, $sentenca_1_instancia, $recurso, $despesas_processuais_1, $despesas_processuais_2, $valor_pago, $economia, $user_id];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $new_id = $pdo->lastInsertId();
                    $response = ['status' => 'success', 'message' => 'Processo adicionado com sucesso!'];

                    // --- LOG 4: CRIAÇÃO DE PROCESSO (SUCESSO) ---
                    $detalhes = "Criou o processo: '$numero_processo' (ID: $new_id).";
                    registrar_log($pdo, 'Criação de Processo (Sucesso)', $user_id, $detalhes);
                    // --- FIM DO LOG 4 ---
                }
            } catch (PDOException $e) {
                $response['message'] = ($e->errorInfo[1] == 1062) ? 'Erro: O número deste processo já existe no sistema.' : 'Erro no banco de dados: ' . $e->getMessage();
                
                // --- LOG 5: CRIAÇÃO/EDIÇÃO (FALHA) ---
                $acao_log = $id ? 'Edição' : 'Criação';
                $id_log = $id ?: '(novo)';
                $detalhes = "Falha ao [$acao_log] o processo: '$numero_processo' (ID: $id_log). Erro: " . $e->getMessage();
                registrar_log($pdo, "Falha $acao_log Processo", $user_id, $detalhes);
                // --- FIM DO LOG 5 ---
            }
        } else {
            $response['message'] = 'Campos obrigatórios (Nº do Processo, Autor, Data) não podem estar vazios.';
        }
    }

    if ($_POST['action'] === 'excluir_processo' && $user_id) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                // --- LOG 6: COLETA DE DADOS ANTES DE EXCLUIR ---
                $stmt_info = $pdo->prepare("SELECT numero_processo, autor FROM processos_judiciais WHERE id = ?");
                $stmt_info->execute([$id]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $log_details_raw = $info ? "{$info['numero_processo']} (Autor: {$info['autor']}, ID: $id)" : "ID: $id (Info não encontrada)";
                // --- FIM DO LOG 6 ---

                $stmt = $pdo->prepare("DELETE FROM processos_judiciais WHERE id = ?");
                $stmt->execute([$id]);
                $response = ['status' => 'success', 'message' => 'Processo excluído com sucesso!'];

                // --- LOG 7: EXCLUSÃO (SUCESSO) ---
                $detalhes = "Excluiu o processo: $log_details_raw.";
                registrar_log($pdo, 'Exclusão de Processo (Sucesso)', $user_id, $detalhes);
                // --- FIM DO LOG 7 ---

            } catch (PDOException $e) {
                $response['message'] = 'Erro ao excluir o processo: ' . $e->getMessage();

                // --- LOG 8: EXCLUSÃO (FALHA) ---
                $detalhes = "Falha ao excluir o processo ID: $id. Erro: " . $e->getMessage();
                registrar_log($pdo, 'Exclusão de Processo (Falha)', $user_id, $detalhes);
                // --- FIM DO LOG 8 ---
            }
        }
    }
    echo json_encode($response);
    exit();
}

// ... [O restante do seu código PHP e HTML permanece o mesmo] ...
if (isset($_GET['action']) && $_GET['action'] === 'get_processo') {
    header('Content-Type: application/json');
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM processos_judiciais WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    } else {
        echo json_encode(null);
    }
    exit();
}

// PARTE 2: BUSCA DE DADOS PARA EXIBIÇÃO DA PÁGINA
$anos_disponiveis = range(date('Y'), 2020);
$meses_nomes = [1=>"Janeiro", 2=>"Fevereiro", 3=>"Março", 4=>"Abril", 5=>"Maio", 6=>"Junho", 7=>"Julho", 8=>"Agosto", 9=>"Setembro", 10=>"Outubro", 11=>"Novembro", 12=>"Dezembro"];

$registros_por_pagina = 15;
$pagina_selecionada = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($pagina_selecionada - 1) * $registros_por_pagina;

$where_conditions = [];
$params = [];
if ($ano_selecionado) {
    $where_conditions[] = "ano = :ano";
    $params[':ano'] = $ano_selecionado;
}
if (!empty($busca)) {
    $where_conditions[] = "(numero_processo LIKE :busca OR autor LIKE :busca OR materia LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}
$where_sql = count($where_conditions) > 0 ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM processos_judiciais" . $where_sql);
    $stmt_total->execute($params);
    $total_registros = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $stmt_lista = $pdo->prepare("SELECT * FROM processos_judiciais" . $where_sql . " ORDER BY data_recebimento DESC, id DESC LIMIT :limit OFFSET :offset");
    $stmt_lista->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt_lista->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) { $stmt_lista->bindValue($key, $value); }
    $stmt_lista->execute();
    $registros = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Gerenciamento de Processos Detalhados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .modal-content { background-color: #fefefe; margin: 5% auto; border-radius: 12px; width: 90%; max-width: 800px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column; animation: fadeIn 0.3s ease-out; }
        .modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.25rem; font-weight: 600; color: #111827; }
        .modal-body { padding: 1.5rem; }
        #form-processo { overflow-y: auto; max-height: 80vh; }
        .modal-footer { padding: 1rem 1.5rem; background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; text-align: right; }
        .close-button { color: #9ca3af; background: transparent; border: none; font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .close-button:hover { color: #111827; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; transition: border-color 0.2s, box-shadow 0.2s; background-color: #fff; }
        .form-control:focus { border-color: var(--cor-primaria); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .form-control[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
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
            <div class="flex justify-between items-center mb-6">
                <h1 id="main-page-title" class="text-2xl font-bold text-gray-800">Gerenciamento de Processos</h1>
                <div id="action-buttons-group" class="flex gap-2">
                    <button id="btn-adicionar" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-plus mr-2"></i>Adicionar Processo
                    </button>
                    <a href="importar_processos_detalhados.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-upload mr-2"></i>Importar Planilha
                    </a>
                    <a href="processos_detalhados.php?action=export&ano=<?php echo $ano_selecionado; ?>&busca=<?php echo urlencode($busca); ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-file-csv mr-2"></i>Exportar CSV
                    </a>
                </div>
            </div>

            <div id="filtro-card" class="bg-white p-4 rounded-lg shadow mb-6">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label id="busca-label" for="busca" class="text-sm font-medium text-gray-700">Buscar:</label>
                        <input type="text" name="busca" id="busca" value="<?php echo htmlspecialchars($busca); ?>" class="form-control mt-1" placeholder="Nº do processo, autor, matéria...">
                    </div>
                    <div>
                        <label id="ano-label" for="ano-filtro" class="text-sm font-medium text-gray-700">Ano:</label>
                        <select name="ano" id="ano-filtro" class="form-control mt-1">
                            <option value="">Todos</option>
                            <?php foreach($anos_disponiveis as $ano_opcao): ?>
                                <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                         <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Filtrar</button>
                         <a href="processos_detalhados.php" id="btn-limpar-filtro" class="w-full text-center bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-4 rounded-lg border border-gray-300">Limpar</a>
                    </div>
                </form>
            </div>

            <div id="table-card" class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead id="table-header" class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="py-3 px-4">Nº do Processo</th>
                            <th class="py-3 px-4">Autor</th>
                            <th class="py-3 px-4">Data Receb.</th>
                            <th class="py-3 px-4">Valor da Causa</th>
                            <th class="py-3 px-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php if (empty($registros)): ?>
                            <tr><td colspan="5" class="text-center py-10 text-gray-500">Nenhum processo encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($registros as $reg): ?>
                            <tr class="border-b">
                                <td class="py-3 px-4 font-medium text-gray-900"><?php echo htmlspecialchars($reg['numero_processo']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($reg['autor']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars(date("d/m/Y", strtotime($reg['data_recebimento']))); ?></td>
                                <td class="py-3 px-4">R$ <?php echo number_format($reg['valor_causa'], 2, ',', '.'); ?></td>
                                <td class="py-3 px-4 text-center">
                                    <button class="btn-edit text-blue-600 hover:text-blue-800 p-1" data-id="<?php echo $reg['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn-delete text-red-600 hover:text-red-800 p-1" data-id="<?php echo $reg['id']; ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
            <div id="paginacao-wrapper" class="pagination">
                <?php if ($pagina_selecionada > 1): ?>
                    <a href="?page=<?php echo $pagina_selecionada - 1; ?>&ano=<?php echo $ano_selecionado; ?>&busca=<?php echo urlencode($busca); ?>">Anterior</a>
                <?php endif; ?>

                <?php 
                $window = 1;
                if ($pagina_selecionada > $window + 2) {
                    echo '<a href="?page=1&ano='.$ano_selecionado.'&busca='.urlencode($busca).'">1</a>';
                    echo '<span class="disabled">...</span>';
                }
                for ($i = max(1, $pagina_selecionada - $window); $i <= min($total_paginas, $pagina_selecionada + $window); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&ano=<?php echo $ano_selecionado; ?>&busca=<?php echo urlencode($busca); ?>" class="<?php echo ($i == $pagina_selecionada) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php
                if ($pagina_selecionada < $total_paginas - $window - 1) {
                    echo '<span class="disabled">...</span>';
                    echo '<a href="?page='.$total_paginas.'&ano='.$ano_selecionado.'&busca='.urlencode($busca).'"> '.$total_paginas.'</a>';
                }
                ?>

                <?php if ($pagina_selecionada < $total_paginas): ?>
                    <a href="?page=<?php echo $pagina_selecionada + 1; ?>&ano=<?php echo $ano_selecionado; ?>&busca=<?php echo urlencode($busca); ?>">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <div class="modal" id="modal-processo">
        <div id="modal-content" class="modal-content">
            <div id="modal-header" class="modal-header">
                <h2 id="modal-title">Adicionar Processo</h2>
                <button class="close-button">&times;</button>
            </div>
            <form id="form-processo">
                <div id="modal-body" class="modal-body">
                    <input type="hidden" name="action" value="salvar_processo">
                    <input type="hidden" name="processo_id" id="processo_id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="form-group md:col-span-2">
                            <label for="numero_processo">Nº do Processo:</label>
                            <input type="text" id="numero_processo" name="numero_processo" class="form-control" required>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="autor">Autor:</label>
                            <input type="text" id="autor" name="autor" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="data_recebimento">Data de Recebimento:</label>
                            <input type="date" id="data_recebimento" name="data_recebimento" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ano-modal">Ano:</label> <input type="number" id="ano-modal" name="ano" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="mes-modal">Mês:</label> <select id="mes-modal" name="mes" class="form-control" required>
                                <?php foreach($meses_nomes as $num => $nome): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="valor_causa">Valor da Causa:</label>
                            <input type="text" id="valor_causa" name="valor_causa" class="form-control" placeholder="Ex: 1500,50">
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="materia">Matéria:</label>
                            <input type="text" id="materia" name="materia" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="central_custo">Central de Custo:</label>
                            <input type="text" id="central_custo" name="central_custo" class="form-control">
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="sentenca_1_instancia">Sentença 1ª Instância:</label>
                            <textarea id="sentenca_1_instancia" name="sentenca_1_instancia" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="recurso">Recurso:</label>
                            <textarea id="recurso" name="recurso" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="despesas_processuais_1">Despesas Processuais 1:</label>
                            <input type="text" id="despesas_processuais_1" name="despesas_processuais_1" class="form-control" placeholder="Ex: 100,00">
                        </div>
                        <div class="form-group">
                            <label for="despesas_processuais_2">Despesas Processuais 2:</label>
                            <input type="text" id="despesas_processuais_2" name="despesas_processuais_2" class="form-control" placeholder="Ex: 100,00">
                        </div>
                        <div class="form-group">
                            <label for="valor_pago">Valor Pago:</label>
                            <input type="text" id="valor_pago" name="valor_pago" class="form-control" placeholder="Ex: 1000,00">
                        </div>
                        <div class="form-group">
                            <label for="economia">Economia:</label>
                            <input type="text" id="economia" name="economia" class="form-control" placeholder="Ex: 500,00">
                        </div>
                    </div>
                </div>
                <div id="modal-footer" class="modal-footer">
                     <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/app.js"></script> 
    <script src="../js/mobile.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ==========================================================
        // PARTE 1: LÓGICA DO MODAL (DO ARQUIVO "ANTES")
        // ==========================================================
        
        const modal = document.getElementById('modal-processo');
        const modalTitle = document.getElementById('modal-title');
        const btnOpen = document.getElementById('btn-adicionar');
        // Adicionando verificação: só busca o 'close-button' se o modal existir
        const btnClose = modal ? modal.querySelector('.close-button') : null;
        const formProcesso = document.getElementById('form-processo');
        const tableBody = document.querySelector('tbody'); // 'tbody' é onde os botões de editar/excluir estão

        const openModal = (title) => {
            if (!modal) return; // Não faz nada se o modal não existir
            modalTitle.textContent = title;
            modal.style.display = 'flex';
            // BOA PRÁTICA: Atualiza o tema do modal sempre que ele abrir
            if(typeof atualizarTemaModal === 'function') {
                atualizarTemaModal();
            }
        };
        
        const closeModal = () => { 
            if (!modal) return;
            modal.style.display = 'none'; 
            if (formProcesso) formProcesso.reset(); 
        };

        // --- Event Listeners do Modal ---

        if (btnClose) {
            btnClose.addEventListener('click', closeModal);
        }
        
        window.addEventListener('click', (event) => { 
            if (event.target == modal) closeModal(); 
        });

        if (btnOpen) {
            btnOpen.addEventListener('click', () => {
                if (formProcesso) {
                    formProcesso.reset();
                    formProcesso.querySelector('#processo_id').value = '';
                }
                openModal('Adicionar Processo');
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const target = event.target;
                const btnEdit = target.closest('.btn-edit');
                const btnDelete = target.closest('.btn-delete');

                // Botão EDITAR
                if (btnEdit) {
                    const id = btnEdit.dataset.id;
                    fetch(`processos_detalhados.php?action=get_processo&id=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data) {
                                formProcesso.querySelector('#processo_id').value = data.id;
                                formProcesso.querySelector('#numero_processo').value = data.numero_processo;
                                formProcesso.querySelector('#autor').value = data.autor;
                                formProcesso.querySelector('#data_recebimento').value = data.data_recebimento;
                                // Corrigindo IDs dos campos 'ano' e 'mes' para corresponder ao seu HTML
                                formProcesso.querySelector('#ano-modal').value = data.ano; 
                                formProcesso.querySelector('#mes-modal').value = data.mes;
                                formProcesso.querySelector('#valor_causa').value = data.valor_causa.replace('.', ',');
                                formProcesso.querySelector('#materia').value = data.materia;
                                formProcesso.querySelector('#central_custo').value = data.central_custo;
                                formProcesso.querySelector('#sentenca_1_instancia').value = data.sentenca_1_instancia;
                                formProcesso.querySelector('#recurso').value = data.recurso;
                                formProcesso.querySelector('#despesas_processuais_1').value = data.despesas_processuais_1.replace('.', ',');
                                formProcesso.querySelector('#despesas_processuais_2').value = data.despesas_processuais_2.replace('.', ',');
                                formProcesso.querySelector('#valor_pago').value = data.valor_pago.replace('.', ',');
                                formProcesso.querySelector('#economia').value = data.economia.replace('.', ',');
                                openModal('Editar Processo');
                            } else {
                                Swal.fire('Erro!', 'Não foi possível encontrar o processo.', 'error');
                            }
                        });
                }

                // Botão EXCLUIR
                if (btnDelete) {
                    const id = btnDelete.dataset.id;
                    Swal.fire({
                        title: 'Tem certeza?',
                        text: "Você não poderá reverter esta ação!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sim, excluir!',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const formData = new FormData();
                            formData.append('action', 'excluir_processo');
                            formData.append('id', id);
                            fetch('processos_detalhados.php', { method: 'POST', body: formData })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        Swal.fire('Excluído!', data.message, 'success').then(() => window.location.reload());
                                    } else {
                                        Swal.fire('Erro!', data.message, 'error');
                                    }
                                });
                        }
                    });
                }
            });
        } // Fim if(tableBody)

        if (formProcesso) {
            formProcesso.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                fetch('processos_detalhados.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            closeModal();
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: data.message, timer: 2000, showConfirmButton: false })
                                .then(() => window.location.reload());
                        } else {
                            Swal.fire('Erro!', data.message, 'error');
                        }
                    });
            });
        } // Fim if(formProcesso)


        // ==========================================================
        // PARTE 2: LÓGICA DO TEMA (DO ARQUIVO "AGORA")
        // ==========================================================

        function atualizarTemaTituloPrincipal() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const el = document.getElementById('main-page-title');
            const cor = isDarkMode ? '#E5E7EB' : '#1F2937'; // gray-200 / gray-800
            if (el) el.style.color = cor;
        }

        // Target 1: Filtro
        function atualizarTemaFiltro() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('filtro-card');
            const buscaLabel = document.getElementById('busca-label');
            const buscaInput = document.getElementById('busca');
            const anoLabel = document.getElementById('ano-label');
            const anoSelect = document.getElementById('ano-filtro');
            const btnLimpar = document.getElementById('btn-limpar-filtro');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corTextoLabel = isDarkMode ? '#A0AEC0' : '#374151'; // gray-400 / gray-700
            const corFundoInput = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoInput = isDarkMode ? '#E2E8F0' : '#111827'; // gray-200 / gray-900
            const corBordaInput = isDarkMode ? '#6B7280' : '#D1D5DB'; // gray-500 / gray-300
            
            // Botão Limpar
            const corFundoBtnLimpar = isDarkMode ? '#4A5568' : '#FFFFFF';
            const corTextoBtnLimpar = isDarkMode ? '#E2E8F0' : '#374151'; // gray-200 / gray-700
            const corBordaBtnLimpar = isDarkMode ? '#6B7280' : '#D1D5DB'; // gray-500 / gray-300
            const corFundoHoverBtnLimpar = isDarkMode ? '#374151' : '#F9FAFB'; // gray-700 / gray-50

            if (card) card.style.backgroundColor = corFundoCard;
            if (buscaLabel) buscaLabel.style.color = corTextoLabel;
            if (anoLabel) anoLabel.style.color = corTextoLabel;
            
            [buscaInput, anoSelect].forEach(el => {
                if (el) {
                    el.style.backgroundColor = corFundoInput;
                    el.style.color = corTextoInput;
                    el.style.borderColor = corBordaInput;
                }
            });

            if(btnLimpar) {
                btnLimpar.style.backgroundColor = corFundoBtnLimpar;
                btnLimpar.style.color = corTextoBtnLimpar;
                btnLimpar.style.borderColor = corBordaBtnLimpar;
                // Hover (melhor via CSS, mas funciona)
                btnLimpar.onmouseover = () => btnLimpar.style.backgroundColor = corFundoHoverBtnLimpar;
                btnLimpar.onmouseout = () => btnLimpar.style.backgroundColor = corFundoBtnLimpar;
            }
        }
        
        // Target 2, 4: Tabela (Hover e Fontes)
        function atualizarTemaTabela() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const card = document.getElementById('table-card');
            const header = document.getElementById('table-header');
            const allRows = document.querySelectorAll('#table-body tr');

            const corFundoCard = isDarkMode ? '#2D3748' : '#FFFFFF';
            const corFundoHeader = isDarkMode ? '#4A5568' : '#F9FAFB'; // gray-600 / gray-50
            const corTextoHeader = isDarkMode ? '#A0AEC0' : '#374151'; // gray-400 / gray-700
            const corBordaRow = isDarkMode ? '#4A5568' : '#E5E7EB';     // gray-600 / gray-200
            const corTextoProcesso = isDarkMode ? '#FFFFFF' : '#111827'; // white / gray-900 (font-medium)
            const corTextoNormal = isDarkMode ? '#CBD5E0' : '#374151';   // gray-300 / gray-700 (Autor, Data, Valor)
            const corTextoFallback = isDarkMode ? '#A0AEC0' : '#6B7280';// gray-400 / gray-500 ("Nenhum processo")
            
            const corFundoRowNormal = 'transparent';
            const corFundoRowHover = isDarkMode ? '#374151' : '#F9FAFB'; // gray-700 / gray-50

            if (card) card.style.backgroundColor = corFundoCard;
            if (header) {
                header.style.backgroundColor = corFundoHeader;
                header.style.color = corTextoHeader;
            }

            if (allRows.length > 0) {
                allRows.forEach(row => {
                    const fallbackCell = row.querySelector('td[colspan="5"]');
                    if (fallbackCell) {
                        row.style.border = 'none';
                        fallbackCell.style.color = corTextoFallback;
                        return; // Pula para a próxima linha
                    }
                    
                    row.style.borderColor = corBordaRow; 
                    row.style.backgroundColor = corFundoRowNormal; // Reseta o fundo

                    // Aplica hover na LINHA INTEIRA
                    row.addEventListener('mouseenter', () => { row.style.backgroundColor = corFundoRowHover; });
                    row.addEventListener('mouseleave', () => { row.style.backgroundColor = corFundoRowNormal; });

                    const tds = row.querySelectorAll('td');
                    tds.forEach((td, index) => {
                        if (index === 0) td.style.color = corTextoProcesso; // Coluna Nº Processo
                        else if (index >= 1 && index <= 3) td.style.color = corTextoNormal; // Autor, Data, Valor
                    });
                });
            }
        }
        
        // Target 3: Modal "Adicionar/Editar Registro"
        function atualizarTemaModal() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const modal = document.getElementById('modal-processo'); // ID correto do modal
            if (!modal) return; // Se o modal não existir, para a função

            const modalContent = document.getElementById('modal-content');
            const modalHeader = document.getElementById('modal-header');
            const modalTitle = document.getElementById('modal-title');
            const closeButton = modal.querySelector('.close-button'); 
            const modalBody = document.getElementById('modal-body');
            const modalFooter = document.getElementById('modal-footer');
            const labels = modal.querySelectorAll('label'); 
            const formControls = modal.querySelectorAll('.form-control'); 

            const corFundoModalContent = isDarkMode ? '#374151' : '#fefefe'; // gray-700 / branco
            const corFundoHeaderFooter = isDarkMode ? '#2D3748' : '#f9fafb'; // gray-800 / gray-50
            const corBorda = isDarkMode ? '#4A5568' : '#e5e7eb';           // gray-600 / gray-200
            const corTextoTitulo = isDarkMode ? '#FFFFFF' : '#111827';       // white / gray-900
            const corTextoLabel = isDarkMode ? '#D1D5DB' : '#374151';       // gray-300 / gray-700
            const corCloseButton = isDarkMode ? '#9CA3AF' : '#9ca3af';     // gray-400 / gray-400
            const corCloseButtonHover = isDarkMode ? '#FFFFFF' : '#111827';  // white / gray-900
            const corFundoInput = isDarkMode ? '#4A5568' : '#fff';         // gray-600 / white
            const corTextoInput = isDarkMode ? '#E5E7EB' : '#111827';       // gray-200 / gray-900
            const corBordaInput = isDarkMode ? '#6B7280' : '#d1d5db';     // gray-500 / gray-300
            const corFundoReadonly = isDarkMode ? '#374151' : '#f3f4f6';   // gray-700 / gray-100

            if(modalContent) modalContent.style.backgroundColor = corFundoModalContent;
            if(modalHeader) {
                modalHeader.style.borderBottomColor = corBorda;
                modalHeader.style.backgroundColor = corFundoHeaderFooter; // Adicionado
            }
            if(modalTitle) modalTitle.style.color = corTextoTitulo;
            if(closeButton) {
                closeButton.style.color = corCloseButton;
                closeButton.onmouseover = () => closeButton.style.color = corCloseButtonHover;
                closeButton.onmouseout = () => closeButton.style.color = corCloseButton;
            }
            if(modalBody) {
                modalBody.style.backgroundColor = corFundoModalContent;
            }
            if(modalFooter) {
                modalFooter.style.backgroundColor = corFundoHeaderFooter;
                modalFooter.style.borderTopColor = corBorda;
            }
            labels.forEach(label => label.style.color = corTextoLabel);
            formControls.forEach(control => {
                control.style.backgroundColor = control.readOnly ? corFundoReadonly : corFundoInput;
                control.style.color = corTextoInput;
                control.style.borderColor = corBordaInput;
            });
        }

        // Target 5: Paginação
        function atualizarTemaPaginacao() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const wrapper = document.getElementById('paginacao-wrapper');
            if (!wrapper) return; 

            const links = wrapper.querySelectorAll('a');
            const disabledSpans = wrapper.querySelectorAll('.disabled');
            
            const corBorda = isDarkMode ? '#4A5568' : '#ddd';
            const corTextoLink = isDarkMode ? '#A0AEC0' : 'var(--cor-primaria)';
            const corTextoDisabled = isDarkMode ? '#718096' : '#aaa';
            const corFundoDisabled = isDarkMode ? '#2D3748' : '#f9f9f9';
            const corTextoActive = '#FFFFFF';
            const corFundoActive = 'var(--cor-primaria)';
            const corFundoHover = isDarkMode ? '#374151' : '#f1f1f1'; // gray-700 / gray-100
            
            links.forEach(link => {
                if (link.classList.contains('active')) {
                    link.style.backgroundColor = corFundoActive;
                    link.style.color = corTextoActive;
                    link.style.borderColor = corFundoActive;
                } else {
                    link.style.color = corTextoLink;
                    link.style.borderColor = corBorda;
                    link.style.backgroundColor = 'transparent';
                    link.onmouseover = () => link.style.backgroundColor = corFundoHover;
                    link.onmouseout = () => link.style.backgroundColor = 'transparent';
                }
            });
            disabledSpans.forEach(span => {
                span.style.color = corTextoDisabled;
                span.style.backgroundColor = corFundoDisabled;
                span.style.borderColor = corBorda;
            });
        }

        // --- 5. EXECUÇÃO E OBSERVADOR ---

        function atualizarTudo() {
            atualizarTemaTituloPrincipal(); 
            atualizarTemaFiltro();
            atualizarTemaTabela();
            atualizarTemaModal(); 
            atualizarTemaPaginacao(); 
        }

        // 1. Roda tudo no carregamento inicial
        atualizarTudo();

        // 2. Cria o observador para mudar o tema
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    atualizarTudo();
                }
            }
        });

        // 3. Inicia o observador
        observer.observe(document.documentElement, { attributes: true });

        // Constante BASE_URL (movida para dentro do DOMContentLoaded)
        const BASE_URL = 'http://localhost/juridico'; 
    });
    </script>
</body>
</html>