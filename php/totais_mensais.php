<?php
// Inicia a sessão se ainda não tiver sido iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR ARQUIVOS ESSENCIAIS
require_once '../includes/verifica_login.php';
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass
// --- LOG: INCLUSÃO DE DEPENDÊNCIAS ---
require_once '../includes/funcoes.php';
// --- FIM DO LOG ---

// --- LOG 1: VERIFICAÇÃO DE NÍVEL DE ACESSO ---
// Esta página permite exportar e manipular dados. Deve ser restrita a Editores (2) ou Admins (3).
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] < 2) { 
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Gestão de Totais Mensais');
    die('Acesso negado. Você não tem permissão para acessar esta página.');
}
// --- FIM DO LOG 1 ---

$admin_id_logado = $_SESSION['user_id']; // ID do usuário logado para logs

// LÓGICA DE EXPORTAÇÃO CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $ano_export = $_GET['ano'] ?? date('Y');

    // --- LOG 2: EXPORTAÇÃO CSV ---
    $detalhes_log_export = "Usuário (ID: $admin_id_logado) exportou o relatório CSV de Totais Mensais para o ano: $ano_export.";
    registrar_log($pdo, 'Exportação CSV (Totais Mensais)', $admin_id_logado, $detalhes_log_export);
    // --- FIM DO LOG 2 ---

    try {
        // ... [Seu código de busca e geração de CSV aqui] ...
        $sql_pivot = "SELECT mes, tipo_vara, quantidade FROM relatorio_mensal_processos WHERE ano = :ano";
        $stmt_pivot = $pdo->prepare($sql_pivot);
        $stmt_pivot->execute(['ano' => $ano_export]);
        $resultados_db = $stmt_pivot->fetchAll(PDO::FETCH_ASSOC);

        $tipos_stmt = $pdo->query("SELECT DISTINCT tipo_vara FROM relatorio_mensal_processos WHERE ano = " . $pdo->quote($ano_export) . " ORDER BY tipo_vara ASC");
        $tipos_de_vara = $tipos_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $dados_pivotados = [];
        foreach ($resultados_db as $row) { 
            $dados_pivotados[$row['tipo_vara']][$row['mes']] = $row['quantidade']; 
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=relatorio_mensal_' . $ano_export . '.csv');

        $output = fopen('php://output', 'w');

        $meses_nomes_short = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
        $header_row = ['Varas'];
        foreach ($meses_nomes_short as $nome_mes) {
            $header_row[] = $nome_mes;
        }
        fputcsv($output, $header_row);

        foreach ($tipos_de_vara as $tipo_vara) {
            $row_data = [$tipo_vara];
            for ($mes = 1; $mes <= 12; $mes++) {
                $row_data[] = $dados_pivotados[$tipo_vara][$mes] ?? '0';
            }
            fputcsv($output, $row_data);
        }

        fclose($output);
        exit();

    } catch (PDOException $e) {
        die("Erro ao gerar o CSV: " . $e->getMessage());
    }
}


// ✅ PARTE 1: PROCESSAMENTO DE AÇÕES CRUD (VIA AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Ação desconhecida.'];
    $user_id = $admin_id_logado; // ID do usuário da sessão

    // --- AÇÃO PARA SALVAR (CRIAR OU ATUALIZAR) ---
    if ($_POST['action'] === 'salvar_manual' && $user_id) {
        $ano = filter_input(INPUT_POST, 'ano', FILTER_VALIDATE_INT);
        $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
        $tipo_vara = trim($_POST['tipo_vara'] ?? '');
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);

        if ($ano && $mes && !empty($tipo_vara) && isset($quantidade)) {
            try {
                $sql = "INSERT INTO relatorio_mensal_processos (ano, mes, tipo_vara, quantidade, usuario_id) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), usuario_id = VALUES(usuario_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ano, $mes, $tipo_vara, $quantidade, $user_id]);
                $response = ['status' => 'success', 'message' => 'Registro salvo com sucesso!'];

                // --- LOG 3: SALVAR REGISTRO (SUCESSO) ---
                $detalhes_log_save = "Registro salvo: Ano=$ano, Mês=$mes, Vara='$tipo_vara', Qtd=$quantidade.";
                registrar_log($pdo, 'Salvar Registro (Totais Mensais)', $user_id, $detalhes_log_save);
                // --- FIM DO LOG 3 ---

            } catch (PDOException $e) {
                $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
                
                // --- LOG 4: SALVAR REGISTRO (FALHA) ---
                $detalhes_log_fail = "Erro ao salvar: Ano=$ano, Mês=$mes, Vara='$tipo_vara'. Erro: " . $e->getMessage();
                registrar_log($pdo, 'Falha ao Salvar (Totais Mensais)', $user_id, $detalhes_log_fail);
                // --- FIM DO LOG 4 ---
            }
        } else {
            $response['message'] = 'Todos os campos são obrigatórios.';
        }
    }

    // --- AÇÃO PARA EXCLUIR TODOS OS REGISTROS DE UMA VARA NO ANO ---
    if ($_POST['action'] === 'excluir_vara' && $user_id) {
        $ano = filter_input(INPUT_POST, 'ano', FILTER_VALIDATE_INT);
        $tipo_vara = trim($_POST['tipo_vara'] ?? '');

        if ($ano && !empty($tipo_vara)) {
            try {
                $sql = "DELETE FROM relatorio_mensal_processos WHERE ano = ? AND tipo_vara = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ano, $tipo_vara]);
                $response = ['status' => 'success', 'message' => "Todos os registros para '" . htmlspecialchars($tipo_vara) . "' em " . $ano . " foram excluídos."];

                // --- LOG 5: EXCLUIR VARA (SUCESSO) ---
                $detalhes_log_delete = "Excluídos todos os registros para a vara: '$tipo_vara' do ano: $ano.";
                registrar_log($pdo, 'Exclusão em Massa (Totais Mensais)', $user_id, $detalhes_log_delete);
                // --- FIM DO LOG 5 ---

            } catch (PDOException $e) {
                $response['message'] = 'Erro ao excluir os registros: ' . $e->getMessage();
                
                // --- LOG 6: EXCLUIR VARA (FALHA) ---
                $detalhes_log_delete_fail = "Erro ao excluir vara: '$tipo_vara', Ano: $ano. Erro: " . $e->getMessage();
                registrar_log($pdo, 'Falha Exclusão em Massa (Totais Mensais)', $user_id, $detalhes_log_delete_fail);
                // --- FIM DO LOG 6 ---
            }
        }
    }
    
    echo json_encode($response);
    exit();
}

// ✅ PARTE 2: BUSCA DE DADOS PARA EXIBIÇÃO DA PÁGINA
// ... [O restante do seu código PHP e HTML permanece o mesmo] ...
$ano_selecionado = $_GET['ano'] ?? date('Y');
$anos_disponiveis = range(date('Y'), 2020);
$meses_nomes_completo = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$meses_nomes_short = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
$dados_pivotados = [];
$tipos_de_vara = [];

try {
    $sql_pivot = "SELECT mes, tipo_vara, quantidade FROM relatorio_mensal_processos WHERE ano = :ano";
    $stmt_pivot = $pdo->prepare($sql_pivot);
    $stmt_pivot->execute(['ano' => $ano_selecionado]);
    $resultados_db = $stmt_pivot->fetchAll(PDO::FETCH_ASSOC);

    $tipos_stmt = $pdo->query("SELECT DISTINCT tipo_vara FROM relatorio_mensal_processos WHERE ano = " . $pdo->quote($ano_selecionado) . " ORDER BY tipo_vara ASC");
    foreach($tipos_stmt->fetchAll(PDO::FETCH_ASSOC) as $tipo) { 
        $tipos_de_vara[] = $tipo['tipo_vara']; 
    }
    
    foreach ($resultados_db as $row) { 
        $dados_pivotados[$row['tipo_vara']][$row['mes']] = $row['quantidade']; 
    }

} catch (PDOException $e) { 
    die("Erro ao conectar ou consultar o banco de dados: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Totais Mensais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: #fefefe; margin: 10% auto; border-radius: 12px; width: 90%; 
            max-width: 550px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            display: flex; flex-direction: column; animation: fadeIn 0.3s ease-out;
        }
        .modal-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: 1.25rem; font-weight: 600; color: #111827; }
        .modal-body { padding: 1.5rem; }
        #form-manual { overflow-y: auto; max-height: 70vh; }
        .modal-footer {
            padding: 1rem 1.5rem; background-color: #f9fafb; border-top: 1px solid #e5e7eb; 
            border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; text-align: right;
        }
        .close-button {
            color: #9ca3af; background: transparent; border: none; font-size: 1.5rem; cursor: pointer; transition: color 0.2s;
        }
        .close-button:hover { color: #111827; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-control {
            width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; 
            transition: border-color 0.2s, box-shadow 0.2s; background-color: #fff;
        }
        .form-control:focus { border-color: var(--cor-primaria); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .form-control[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
        .editable-cell { cursor: pointer; transition: background-color 0.2s; border-radius: 4px; }
        .editable-cell:hover { background-color: #eff6ff; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Gestão de Totais Mensais</h1>
                <div class="flex gap-2">
                    <button id="btn-adicionar-manual" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-plus mr-2"></i>Adicionar Registro
                    </button>
                    <a href="importar_totais_mensais.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-upload mr-2"></i>Importar Dados
                    </a>
                    <a href="totais_mensais.php?action=export&ano=<?php echo $ano_selecionado; ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-file-csv mr-2"></i>Exportar CSV
                    </a>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form action="" method="GET" class="flex items-center gap-4">
                    <label for="ano-filtro" class="text-sm font-medium text-gray-700">Filtrar por Ano:</label>
                    <select name="ano" id="ano-filtro" class="p-2 border-gray-300 rounded-md shadow-sm">
                        <?php foreach($anos_disponiveis as $ano_opcao): ?>
                            <option value="<?php echo $ano_opcao; ?>" <?php echo ($ano_opcao == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $ano_opcao; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Filtrar</button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <table id="pivot-table" class="w-full text-sm text-left table-auto">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="py-3 px-4">Varas</th>
                            <?php foreach ($meses_nomes_short as $nome_mes): ?>
                                <th class="py-3 px-2 text-center"><?php echo $nome_mes; ?></th>
                            <?php endforeach; ?>
                            <th class="py-3 px-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tipos_de_vara)): ?>
                            <tr><td colspan="14" class="text-center py-4">Nenhum dado encontrado para o ano de <?php echo htmlspecialchars($ano_selecionado); ?>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tipos_de_vara as $tipo_vara): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium text-gray-900"><?php echo htmlspecialchars($tipo_vara); ?></td>
                                <?php for ($mes = 1; $mes <= 12; $mes++): 
                                    $quantidade = $dados_pivotados[$tipo_vara][$mes] ?? 0;
                                ?>
                                    <td class="py-3 px-2 text-center editable-cell" data-ano="<?php echo $ano_selecionado; ?>" data-mes="<?php echo $mes; ?>" data-vara="<?php echo htmlspecialchars($tipo_vara); ?>" data-quantidade="<?php echo $quantidade; ?>">
                                        <?php echo $quantidade; ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="py-3 px-4 text-center">
                                    <button class="btn-delete-vara text-red-600 hover:text-red-800 p-1" data-ano="<?php echo $ano_selecionado; ?>" data-vara="<?php echo htmlspecialchars($tipo_vara); ?>" title="Excluir todos os registros de <?php echo htmlspecialchars($tipo_vara); ?> em <?php echo $ano_selecionado; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal" id="modal-adicao-manual">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Adicionar/Editar Registro</h2>
                <button class="close-button">&times;</button>
            </div>
            <form id="form-manual">
                <div class="modal-body">
                    <input type="hidden" name="action" value="salvar_manual">
                    <div class="form-group">
                        <label for="ano">Ano:</label>
                        <input type="number" id="ano" name="ano" class="form-control" readonly required>
                    </div>
                    <div id="mes-select-container" class="form-group">
                        <label for="mes">Mês:</label>
                        <select id="mes" name="mes" class="form-control" required>
                            <?php foreach ($meses_nomes_completo as $num => $nome): if($num === 0) continue; ?>
                                <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="mes-text-container" class="form-group">
                        <label for="mes-text">Mês:</label>
                        <input type="text" id="mes-text" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="tipo_vara">Tipo de Vara:</label>
                        <input type="text" id="tipo_vara" name="tipo_vara" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="quantidade">Quantidade:</label>
                        <input type="number" id="quantidade" name="quantidade" class="form-control" value="0" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                     <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Salvar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-adicao-manual');
        const modalTitle = document.getElementById('modal-title');
        const btnOpen = document.getElementById('btn-adicionar-manual');
        const btnClose = modal.querySelector('.close-button');
        const formManual = document.getElementById('form-manual');
        const pivotTable = document.getElementById('pivot-table');
        const mesesNomes = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
        
        const mesSelectContainer = document.getElementById('mes-select-container');
        const mesTextContainer = document.getElementById('mes-text-container');

        const openModal = (title) => {
            modalTitle.textContent = title;
            modal.style.display = 'flex'; // Changed to flex for centering
        };
        const closeModal = () => { modal.style.display = 'none'; formManual.reset(); };

        btnClose.addEventListener('click', closeModal);
        window.addEventListener('click', (event) => { if (event.target == modal) closeModal(); });

        // Abrir modal para ADICIONAR um novo TIPO DE VARA
        btnOpen.addEventListener('click', () => {
            formManual.reset();
            formManual.querySelector('#ano').value = document.getElementById('ano-filtro').value;
            formManual.querySelector('#ano').readOnly = true;
            formManual.querySelector('#tipo_vara').readOnly = false;
            
            mesSelectContainer.style.display = 'block';
            mesTextContainer.style.display = 'none';

            openModal('Adicionar Novo Registro');
        });

        // Lidar com cliques na tabela (células e botões de exclusão)
        pivotTable.addEventListener('click', function(event) {
            const target = event.target;
            const editableCell = target.closest('.editable-cell');
            const deleteButton = target.closest('.btn-delete-vara');

            // Clicou em uma CÉLULA EDITÁVEL
            if (editableCell) {
                const ano = editableCell.dataset.ano;
                const mes = editableCell.dataset.mes;
                const vara = editableCell.dataset.vara;
                const quantidade = editableCell.dataset.quantidade;

                formManual.querySelector('#ano').value = ano;
                formManual.querySelector('#mes').value = mes;
                formManual.querySelector('#tipo_vara').value = vara;
                formManual.querySelector('#quantidade').value = quantidade;
                
                formManual.querySelector('#ano').readOnly = true;
                formManual.querySelector('#tipo_vara').readOnly = true;

                mesSelectContainer.style.display = 'none';
                mesTextContainer.style.display = 'block';
                mesTextContainer.querySelector('#mes-text').value = mesesNomes[mes];

                openModal('Editar Quantidade');
            }

            // Clicou no botão de EXCLUIR VARA
            if (deleteButton) {
                const ano = deleteButton.dataset.ano;
                const vara = deleteButton.dataset.vara;

                Swal.fire({
                    title: 'Tem certeza?',
                    text: `Isso excluirá TODOS os 12 registros mensais para "${vara}" em ${ano}. Você não poderá reverter esta ação!`, 
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sim, excluir tudo!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'excluir_vara');
                        formData.append('ano', ano);
                        formData.append('tipo_vara', vara);

                        fetch('totais_mensais.php', { method: 'POST', body: formData })
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

        // Lidar com o SUBMIT do formulário (para criar e editar)
        formManual.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);

            fetch('totais_mensais.php', { method: 'POST', body: formData })
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
    });
    const BASE_URL = 'http://localhost/juridico'; 
    </script>
</body>
</html>