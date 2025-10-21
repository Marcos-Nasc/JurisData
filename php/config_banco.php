<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 1. Verificação de login DEVE vir primeiro
require_once '../includes/verifica_login.php';


// --- INÍCIO DA INCLUSÃO SEGURA DE LOG (SOLUÇÃO "OVO/GALINHA") ---
$log_disponivel = false;
$pdo = null;
$feedback_conexao_quebrada = ''; // Mensagem de aviso se a conexão atual falhar

try {
    // 2. Tenta incluir a conexão atual
    require_once '../includes/conexao.php';
    require_once '../includes/carregar_tema.php'; // Define $themeClass
    // 3. Se a conexão funcionou ($pdo existe), carrega as funções de log
    require_once '../includes/funcoes.php';
    $log_disponivel = true;
} catch (PDOException $e) {
    // A conexão atual está quebrada. O log não funcionará.
    // A página DEVE continuar funcionando para que o admin possa consertar.
    $feedback_conexao_quebrada = "Atenção: A conexão atual com o banco de dados falhou. Os logs de atividade para esta página estão desativados. Erro: " . $e->getMessage();
}

/**
 * Wrapper de log: Só registra se a conexão atual ($pdo) estiver funcionando.
 */
function log_config_banco($acao, $detalhes = null) {
    global $pdo, $log_disponivel;
    if ($log_disponivel && $pdo) {
        // Passa o ID do admin logado
        registrar_log($pdo, $acao, $_SESSION['user_id'] ?? null, $detalhes);
    }
}
// --- FIM DA INCLUSÃO SEGURA DE LOG ---


// --- LOG 1: VERIFICAÇÃO DE NÍVEL DE ADMIN ---
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    log_config_banco('Tentativa de acesso não autorizado', 'Página: Configuração do Banco de Dados');
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}

// Loga o acesso normal do admin
log_config_banco('Acesso à página', 'Admin acessou a página de Configuração do Banco de Dados.');
// --- FIM DO LOG 1 ---


$feedback = ['type' => '', 'message' => ''];
$config_file_path = __DIR__ . '/../includes/conexao.php';

// --- LER CONFIGURAÇÕES ATUAIS ---
$current_config = ['host' => 'localhost', 'dbname' => '', 'user' => 'root', 'pass' => ''];
if (file_exists($config_file_path)) {
    $config_content = file_get_contents($config_file_path);
    preg_match("/\$host\s*=\s*'(.*?)';/", $config_content, $host_match);
    preg_match("/\$dbname\s*=\s*'(.*?)';/", $config_content, $dbname_match);
    preg_match("/\$user\s*=\s*'(.*?)';/", $config_content, $user_match);
    if (isset($host_match[1])) $current_config['host'] = $host_match[1];
    if (isset($dbname_match[1])) $current_config['dbname'] = $dbname_match[1];
    if (isset($user_match[1])) $current_config['user'] = $user_match[1];
    // Não lemos a senha por segurança, mas ela estará disponível pela inclusão do conexao.php
}

// --- PROCESSAR FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê apenas a 'action'. As outras variáveis serão lidas dentro dos IFs.
    $action = $_POST['action'] ?? '';

    // --- LOG 2: AÇÃO TESTAR CONEXÃO (AJAX) ---
    if ($action === 'testar_conexao') {
        // ** CORREÇÃO: Variáveis lidas aqui dentro **
        $new_host = trim($_POST['host'] ?? '');
        $new_dbname = trim($_POST['dbname'] ?? '');
        $new_user = trim($_POST['user'] ?? '');
        $new_pass = $_POST['pass'] ?? '';

        header('Content-Type: application/json');
        
        $detalhes_log_teste = "Host: {$new_host}, DB: {$new_dbname}, User: {$new_user}";
        
        try {
            new PDO("mysql:host={$new_host};dbname={$new_dbname};charset=utf8", $new_user, $new_pass);
            
            log_config_banco('Teste de Conexão (Sucesso)', $detalhes_log_teste);
            echo json_encode(['status' => 'success', 'message' => 'Conexão bem-sucedida!']);
            
        } catch (PDOException $e) {
            log_config_banco('Teste de Conexão (Falha)', $detalhes_log_teste . ". Erro: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Falha na conexão: ' . $e->getMessage()]);
        }
        exit();
    }
    // --- FIM DO LOG 2 ---

    // --- LOG 3: AÇÃO SALVAR CONEXÃO ---
    if ($action === 'salvar_conexao') {
        
        // ** CORREÇÃO: Variáveis lidas aqui dentro **
        $new_host = trim($_POST['host'] ?? '');
        $new_dbname = trim($_POST['dbname'] ?? '');
        $new_user = trim($_POST['user'] ?? '');
        $new_pass = $_POST['pass'] ?? ''; 

        $senha_info_log = empty($new_pass) ? "(Mantida)" : "(Alterada)";
        $detalhes_log_salvar = "Host: {$new_host}, DB: {$new_dbname}, User: {$new_user}, Senha: {$senha_info_log}";

        try {
            // 1. Testa a nova conexão
            new PDO("mysql:host={$new_host};dbname={$new_dbname};charset=utf8", $new_user, $new_pass);
            
            // 2. Prepara o novo conteúdo do arquivo
            $new_config_content = "<?php\n";
            $new_config_content .= "\$host = '{$new_host}';\n";
            $new_config_content .= "\$dbname = '{$new_dbname}'; // nome do banco de dados\n";
            $new_config_content .= "\$user = '{$new_user}'; // seu usuário do MySQL\n";
            $new_config_content .= "\$pass = '{$new_pass}'; // sua senha do MySQL\n\n";
            $new_config_content .= "try {\n";
            $new_config_content .= "    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8\", \$user, \$pass);\n";
            $new_config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $new_config_content .= "} catch (PDOException \$e) {\n";
            $new_config_content .= "    die(\"Erro na conexão: \" . \$e->getMessage());\n";
            $new_config_content .= "}\n?>";

            // 3. Tenta salvar o arquivo
            if (file_put_contents($config_file_path, $new_config_content)) {
                $feedback = ['type' => 'success', 'message' => 'Configurações salvas com sucesso!'];
                $current_config = ['host' => $new_host, 'dbname' => $new_dbname, 'user' => $new_user];
                log_config_banco('Config. Banco Salva (Sucesso)', "Arquivo conexao.php sobrescrito com: " . $detalhes_log_salvar);
            } else {
                $feedback = ['type' => 'error', 'message' => 'Falha ao salvar o arquivo de configuração. Verifique as permissões.'];
                log_config_banco('Config. Banco Salva (Falha Escrita)', "Tentou salvar: " . $detalhes_log_salvar . ". Erro: Falha ao escrever arquivo (permissões?).");
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'error', 'message' => 'Falha na conexão: ' . $e->getMessage() . ' As configurações NÃO foram salvas.'];
            log_config_banco('Config. Banco Salva (Falha Conexão)', "Tentou salvar: " . $detalhes_log_salvar . ". Erro: " . $e->getMessage());
        }
    }
    // --- FIM DO LOG 3 ---

    // --- LOG 4: AÇÃO CRIAR BACKUP ---
    if ($action === 'criar_backup') {

        // Verifica se a conexão principal (e as variáveis) estão disponíveis
        if (!$log_disponivel || !$pdo) {
            $feedback = ['type' => 'error', 'message' => 'Não é possível criar o backup. A conexão principal com o banco de dados falhou. Verifique as configurações e tente novamente.'];
            log_config_banco('Tentativa de Backup (Falha)', 'Conexão principal com o BD indisponível.');
        
        } else {
            // ** IMPORTANTE: Verifique este caminho **
            // Este deve ser o caminho para o executável 'mysqldump' no seu servidor XAMPP.
            $mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe';

            if (!file_exists($mysqldump_path)) {
                 $feedback = ['type' => 'error', 'message' => 'Erro: O executável mysqldump não foi encontrado no caminho especificado: ' . htmlspecialchars($mysqldump_path) . '. Ajuste o caminho na linha 140 do arquivo config_banco.php.'];
                 log_config_banco('Tentativa de Backup (Falha)', 'mysqldump.exe não encontrado em ' . $mysqldump_path);
            } else {

                try {
                    // As variáveis $host, $dbname, $user, e $pass vêm do '../includes/conexao.php'
                    // incluído no topo desta página.
                    
                    $filename = "backup_" . $dbname . "_" . date("Y-m-d_H-i-s") . ".sql";

                    // Constrói o comando de forma segura
                    // NOTA: Passar a senha na linha de comando pode ser um risco de segurança em servidores compartilhados.
                    $command = sprintf('%s --user=%s --password=%s --host=%s %s',
                        escapeshellarg($mysqldump_path),
                        escapeshellarg($user),
                        escapeshellarg($pass),
                        escapeshellarg($host),
                        escapeshellarg($dbname)
                    );

                    // Limpa qualquer saída de buffer anterior (evita corromper o arquivo)
                    if (ob_get_level()) {
                        ob_end_clean();
                    }

                    // Define os headers para forçar o download
                    header('Content-Type: application/sql; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');

                    // Executa o comando e envia a saída diretamente para o browser
                    $return_var = NULL;
                    passthru($command, $return_var);

                    // Loga a ação
                    if ($return_var === 0) {
                        log_config_banco('Backup Criado (Sucesso)', "Download do backup '{$filename}' iniciado.");
                    } else {
                        // Se falhar, não podemos mais enviar $feedback pois os headers já foram enviados.
                        // O log é a única forma de registrar o erro.
                        log_config_banco('Backup Criado (Falha)', "mysqldump falhou com código de retorno: {$return_var}");
                    }
                    
                    // Impede que o restante do HTML seja renderizado
                    exit();

                } catch (Exception $e) {
                    $feedback = ['type' => 'error', 'message' => 'Ocorreu um erro inesperado: ' . $e->getMessage()];
                    log_config_banco('Backup Criado (Falha)', 'Exceção: ' . $e->getMessage());
                }
            }
        }
    }
    // --- FIM DO LOG 4 ---
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Configuração do Banco de Dados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Configuração do Banco de Dados</h1>

            <?php if (!empty($feedback_conexao_quebrada)): ?>
                <div class="p-4 mb-6 text-sm border-l-4 bg-yellow-100 border-yellow-500 text-yellow-700" role="alert">
                    <p class="font-bold">Aviso de Conexão</p>
                    <p><?php echo htmlspecialchars($feedback_conexao_quebrada); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($feedback['message']): ?>
                <div class="p-4 mb-6 text-sm border-l-4 <?php echo ($feedback['type'] === 'success') ? 'bg-green-100 border-green-500 text-green-700' : (($feedback['type'] === 'info') ? 'bg-blue-100 border-blue-500 text-blue-700' : 'bg-red-100 border-red-500 text-red-700'); ?>" role="alert">
                    <?php echo htmlspecialchars($feedback['message']); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Editar Conexão</h2>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Atenção: Risco Elevado!</p>
                    <p>Alterar estas configurações incorretamente pode fazer com que todo o sistema pare de funcionar.</p>
                </div>
                <form id="form-conexao" action="" method="POST">
                    <input type="hidden" name="action" value="salvar_conexao">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group"><label for="host" class="block font-medium text-gray-700">Host:</label><input type="text" id="host" name="host" value="<?php echo htmlspecialchars($current_config['host']); ?>" class="mt-1 form-control w-full p-2 border rounded-md"></div>
                        <div class="form-group"><label for="dbname" class="block font-medium text-gray-700">Nome do Banco (Database):</label><input type="text" id="dbname" name="dbname" value="<?php echo htmlspecialchars($current_config['dbname']); ?>" class="mt-1 form-control w-full p-2 border rounded-md"></div>
                        <div class="form-group"><label for="user" class="block font-medium text-gray-700">Usuário:</label><input type="text" id="user" name="user" value="<?php echo htmlspecialchars($current_config['user']); ?>" class="mt-1 form-control w-full p-2 border rounded-md"></div>
                        <div class="form-group"><label for="pass" class="block font-medium text-gray-700">Senha:</label><input type="password" id="pass" name="pass" placeholder="********" class="mt-1 form-control w-full p-2 border rounded-md"><p class="text-xs text-gray-500 mt-1">Deixe em branco para não alterar. A senha atual não é exibida.</p></div>
                    </div>
                    <div class="mt-6 flex justify-end gap-4">
                        <button type="button" id="btn-testar" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                            <i class="fas fa-plug mr-2"></i>Testar Conexão
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                            <i class="fas fa-save mr-2"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Backup</h2>
                <p class="text-gray-600 mb-4">Crie um backup completo do banco de dados. O arquivo gerado será um `.sql`.</p>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="criar_backup">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <i class="fas fa-database mr-2"></i>Criar Backup do Banco de Dados
                    </button>
                </form>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Seu JavaScript (sem alterações)
    document.addEventListener('DOMContentLoaded', function() {
        const btnTestar = document.getElementById('btn-testar');
        const formConexao = document.getElementById('form-conexao');

        btnTestar.addEventListener('click', function() {
            const formData = new FormData(formConexao);
            formData.append('action', 'testar_conexao');

            fetch('config_banco.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Sucesso!', data.message, 'success');
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro de comunicação!', 'Não foi possível contatar o servidor.', 'error');
            });
        });
    });
    const BASE_URL = 'http://localhost/juridico'; 
    </script>
    <script src="../js/script.js"></script>
</body>
</html>