<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass
// --- LOG: INCLUSÃO DE DEPENDÊNCIAS ---
require_once '../includes/funcoes.php';
// --- FIM DO LOG ---

// --- LOG 1: TENTATIVA DE ACESSO NÃO AUTORIZADO ---
// Apenas Admins podem acessar esta página
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Gerenciamento de Usuários');
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}
// --- FIM DO LOG 1 ---

$feedback = ['type' => '', 'message' => ''];
$admin_id_logado = $_SESSION['user_id']; // Para facilitar o log

// Lidar com POST para adicionar/editar/excluir usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $cargo = $_POST['cargo'] ?? '';
    $nivel = $_POST['nivel'] ?? 1;
    $status = $_POST['status'] ?? '1';

    try {
        if ($action === 'add') {
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo, nivel, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $hashed_password, $cargo, $nivel, $status]);
            
            // --- LOG 2: CRIAÇÃO DE USUÁRIO (SUCESSO) ---
            $novo_user_id = $pdo->lastInsertId();
            $detalhes = "Admin (ID: $admin_id_logado) criou o usuário ID $novo_user_id: $nome (Email: $email, Nível: $nivel, Status: $status).";
            registrar_log($pdo, 'Criação de Usuário (Sucesso)', $admin_id_logado, $detalhes);
            // --- FIM DO LOG 2 ---

            $feedback = ['type' => 'success', 'message' => 'Usuário adicionado com sucesso!'];

        } elseif ($action === 'edit') {
            // --- LOG 3: EDIÇÃO DE USUÁRIO (SUCESSO) ---
            $log_senha_info = "(Senha Mantida)"; // Info para o log
            
            if ($senha) {
                $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, cargo = ?, nivel = ?, status = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $hashed_password, $cargo, $nivel, $status, $id]);
                $log_senha_info = "(Senha Alterada)"; // Atualiza info do log
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, cargo = ?, nivel = ?, status = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $cargo, $nivel, $status, $id]);
            }
            
            $detalhes = "Admin (ID: $admin_id_logado) editou o usuário ID $id. ";
            $detalhes .= "Novos dados: Nome: $nome, Email: $email, Nível: $nivel, Status: $status. $log_senha_info";
            registrar_log($pdo, 'Edição de Usuário (Sucesso)', $admin_id_logado, $detalhes);
            // --- FIM DO LOG 3 ---
            
            $feedback = ['type' => 'success', 'message' => 'Usuário atualizado com sucesso!'];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'error', 'message' => 'Erro ao salvar usuário: ' . $e->getMessage()];

        // --- LOG 4: CRIAÇÃO/EDIÇÃO (FALHA) ---
        $acao_log = ($action === 'add') ? 'Criação de Usuário (Falha)' : 'Edição de Usuário (Falha)';
        $detalhes = "Admin (ID: $admin_id_logado) falhou ao tentar '$action' o usuário (Nome: $nome, Email: $email). Erro: " . $e->getMessage();
        registrar_log($pdo, $acao_log, $admin_id_logado, $detalhes);
        // --- FIM DO LOG 4 ---
    }
}

// Lidar com GET para excluir usuário
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // --- LOG 5: EXCLUSÃO DE USUÁRIO ---
    try {
        // Pega os dados do usuário ANTES de excluir, para o log
        $stmt_user = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $stmt_user->execute([$id]);
        $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $user_log_info = $user_info ? "{$user_info['nome']} (Email: {$user_info['email']}, ID: $id)" : "ID: $id (Info não encontrada)";

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        
        // (Sucesso)
        $detalhes = "Admin (ID: $admin_id_logado) excluiu o usuário: $user_log_info.";
        registrar_log($pdo, 'Exclusão de Usuário (Sucesso)', $admin_id_logado, $detalhes);

        header("Location: configuracoes_usuarios.php?delete_success=1");
        exit;
    } catch (PDOException $e) {
        // (Falha)
        $detalhes = "Admin (ID: $admin_id_logado) falhou ao excluir usuário ID $id. Erro: " . $e->getMessage();
        registrar_log($pdo, 'Exclusão de Usuário (Falha)', $admin_id_logado, $detalhes);
        
        $feedback = ['type' => 'error', 'message' => 'Erro ao excluir usuário: ' . $e->getMessage()];
    }
    // --- FIM DO LOG 5 ---
}

if(isset($_GET['delete_success'])) {
    $feedback = ['type' => 'success', 'message' => 'Usuário excluído com sucesso!'];
}


// Lógica para buscar todos os usuários (sem alteração)
$stmt = $pdo->query("SELECT id, nome, email, cargo, nivel, status FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Gerenciamento de Usuários</title>
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
        #userForm { overflow-y: auto; max-height: 70vh; }
        .modal-footer {
            padding: 1rem 1.5rem; background-color: #f9fafb; border-top: 1px solid #e5e7eb; 
            border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;
            display: flex; justify-content: flex-end; gap: 0.75rem;
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
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Gerenciamento de Usuários</h1>

            <?php if ($feedback['message']): ?>
                <div class="p-4 mb-6 text-sm border-l-4 <?php echo ($feedback['type'] === 'success') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars(trim($feedback['message'])); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Todos os Usuários</h2>
                    <button onclick="openModal('add')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-plus mr-2"></i>Adicionar Novo Usuário
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nível</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($usuario['cargo']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($usuario['nivel']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $usuario['status'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $usuario['status'] == 1 ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button onclick='openModal("edit", <?php echo json_encode($usuario); ?>)' class="text-indigo-600 hover:text-indigo-800 p-1" title="Editar Usuário">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $usuario['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este usuário?');" class="text-red-600 hover:text-red-800 p-1 ml-2" title="Excluir Usuário">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Adicionar Usuário</h2>
                <button type="button" class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm" action="configuracoes_usuarios.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="id" id="modalUserId">
                    
                    <div class="form-group">
                        <label for="modalNome">Nome</label>
                        <input type="text" name="nome" id="modalNome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="modalEmail">Email</label>
                        <input type="email" name="email" id="modalEmail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="modalSenha">Senha</label>
                        <input type="password" name="senha" id="modalSenha" class="form-control" placeholder="Deixe em branco para não alterar">
                    </div>
                    <div class="form-group">
                        <label for="modalCargo">Cargo</label>
                        <input type="text" name="cargo" id="modalCargo" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="modalNivel">Nível</label>
                        <select name="nivel" id="modalNivel" class="form-control" required>
                            <option value="1">Usuário</option>
                            <option value="2">Editor</option>
                            <option value="3">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modalStatus">Status</label>
                        <select name="status" id="modalStatus" class="form-control" required>
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button type="submit" class="py-2 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalAction = document.getElementById('modalAction');
        const modalUserId = document.getElementById('modalUserId');
        const modalTitle = document.getElementById('modal-title');
        const modalNome = document.getElementById('modalNome');
        const modalEmail = document.getElementById('modalEmail');
        const modalSenha = document.getElementById('modalSenha');
        const modalCargo = document.getElementById('modalCargo');
        const modalNivel = document.getElementById('modalNivel');
        const modalStatus = document.getElementById('modalStatus');
        const userForm = document.getElementById('userForm');

        function openModal(action, user = null) {
            userForm.reset();
            modalAction.value = action;

            if (action === 'add') {
                modalTitle.innerText = 'Adicionar Novo Usuário';
                modalUserId.value = '';
                modalSenha.required = true;
            } else if (action === 'edit' && user) {
                modalTitle.innerText = 'Editar Usuário';
                modalUserId.value = user.id;
                modalNome.value = user.nome;
                modalEmail.value = user.email;
                modalCargo.value = user.cargo;
                modalNivel.value = user.nivel;
                modalStatus.value = user.status;
                modalSenha.required = false;
                modalSenha.placeholder = "Deixe em branco para não alterar";
            }
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }
        
        // Fecha o modal se o usuário clicar fora do conteúdo
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        const BASE_URL = 'http://localhost/juridico'; 
    </script>
    <script src="../js/script.js"></script>
</body>
</html>