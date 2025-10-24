<?php
// Pega o nome da página atual para destacar o ícone
$pagina_atual = basename($_SERVER['PHP_SELF']);

// Define quais ícones ficam ativos para quais páginas
$relatorios_paginas = ['performance_financeira.php', 'provisao_risco.php', 'volume_entrada.php'];
$gestao_paginas = ['totais_mensais.php', 'processos_detalhados.php'];
$config_paginas = ['configuracoes_gerais.php', 'aparencia.php', 'manutencao.php', 'configuracoes_usuarios.php', 'config_banco.php', 'logs.php'];

$dashboard_active = ($pagina_atual == 'index.php') ? 'active' : '';
$relatorios_active = in_array($pagina_atual, $relatorios_paginas) ? 'active' : '';
$gestao_active = in_array($pagina_atual, $gestao_paginas) ? 'active' : '';
$config_active = in_array($pagina_atual, $config_paginas) ? 'active' : '';

// Pega o Título da Página
$titulo_pagina = "Dashboard"; // Padrão
if ($relatorios_active) $titulo_pagina = "Relatórios";
if ($gestao_active) $titulo_pagina = "Gestão";
if ($config_active) $titulo_pagina = "Configuração";

?>

<nav class="mobile-bottom-nav">
    <a href="#" id="open-sheet-relatorios" class="nav-item <?php echo $relatorios_active; ?>">
        <i class="fas fa-chart-bar"></i>
        </a>
    <a href="#" id="open-sheet-gestao" class="nav-item <?php echo $gestao_active; ?>">
        <i class="fas fa-wallet"></i>
        </a>
    
    <a href="<?php echo BASE_URL; ?>/index.php" class="nav-item fab <?php echo $dashboard_active; ?>">
        <img src="<?php echo BASE_URL; ?>/images/logo_icone.png" alt="Dashboard" class="fab-logo-icon">
    </a>
    
    <a href="#" id="open-sheet-config" class="nav-item <?php echo $config_active; ?>">
        <i class="fas fa-cog"></i>
        </a>
    <a href="#" id="open-sheet-profile" class="nav-item">
        <div class="user-avatar-nav" style="background-color: <?php echo $avatar_color; ?>;">
             <span><?php echo htmlspecialchars($user_initials); ?></span>
        </div>
        </a>
</nav>


<div id="sheet-relatorios" class="mobile-bottom-sheet">
    <div class="sheet-header">
        <h3 class="sheet-title">Relatórios</h3>
        <button class="close-sheet" data-sheet="sheet-relatorios">&times;</button>
    </div>
    <ul class="sheet-list">
        <li><a href="<?php echo BASE_URL; ?>/php/performance_financeira.php">Performance Financeira</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/provisao_risco.php">Provisão de Risco</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/volume_entrada.php">Volume de Entrada</a></li>
    </ul>
</div>

<div id="sheet-gestao" class="mobile-bottom-sheet">
    <div class="sheet-header">
        <h3 class="sheet-title">Gestão</h3>
        <button class="close-sheet" data-sheet="sheet-gestao">&times;</button>
    </div>
    <ul class="sheet-list">
        <li><a href="<?php echo BASE_URL; ?>/php/totais_mensais.php">Totais Mensais</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/processos_detalhados.php">Processos Detalhados</a></li>
    </ul>
</div>

<div id="sheet-config" class="mobile-bottom-sheet">
    <div class="sheet-header">
        <h3 class="sheet-title">Configuração</h3>
        <button class="close-sheet" data-sheet="sheet-config">&times;</button>
    </div>
    <ul class="sheet-list scrollable">
        <li><a href="<?php echo BASE_URL; ?>/php/configuracoes_gerais.php">Configurações Gerais</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/aparencia.php">Aparência</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/manutencao.php">Manutenção</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/configuracoes_usuarios.php">Usuários</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/config_banco.php">Banco de Dados</a></li>
        <li><a href="<?php echo BASE_URL; ?>/php/logs.php">Logs de Atividade</a></li>
    </ul>
</div>


<div id="sheet-profile" class="mobile-bottom-sheet">
    <div class="sheet-header">
        <h3 class="sheet-title">Perfil e Opções</h3>
        <button class="close-sheet" data-sheet="sheet-profile">&times;</button>
    </div>
    
    <div class="profile-sheet-header">
        <div class="user-avatar-mobile" style="background-color: <?php echo $avatar_color; ?>; width: 48px; height: 48px; font-size: 1.2rem;">
            <span><?php echo htmlspecialchars($user_initials); ?></span>
        </div>
        <div class="profile-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nome_usuario'] ?? 'Usuário'); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['cargo_usuario'] ?? 'Cargo'); ?></span>
        </div>
    </div>
    
    <ul class="sheet-list">
        <li class="profile-menu-item">
            <span>Modo Escuro</span>
            <label class="switch">
                <input type="checkbox" id="theme-toggle-mobile">
                <span class="slider"></span>
            </label>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/includes/logout.php" class="profile-menu-item danger">
                <span>Sair</span>
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </li>
    </ul>
</div>