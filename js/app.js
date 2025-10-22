document.addEventListener('DOMContentLoaded', function() {
    
    // ==================================================================
    // LÓGICA GLOBAL: TEMA (MODO ESCURO)
    // ==================================================================
    const themeToggle = document.getElementById('theme-toggle');
    const THEME_KEY = 'theme'; // Chave para salvar no localStorage

    // 1. Aplica o tema salvo no carregamento da página
    function applySavedTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        
        // Se tem algo salvo, usa
        if (savedTheme) {
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            if (themeToggle) {
                themeToggle.checked = (savedTheme === 'dark');
            }
        } else {
            // Se não tem nada salvo, usa o padrão do sistema (do navegador)
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', prefersDark);
            if (themeToggle) {
                themeToggle.checked = prefersDark;
            }
        }
    }

    // 2. Salva a escolha do usuário ao clicar no toggle
    if (themeToggle) {
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.documentElement.classList.add('dark');
                localStorage.setItem(THEME_KEY, 'dark'); // Salva "dark"
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem(THEME_KEY, 'light'); // Salva "light"
            }
        });
    }
    
    // 3. Executa a função ao carregar
    applySavedTheme();

    
    // ==================================================================
    // LÓGICA GLOBAL: SIDEBAR RESPONSIVA
    // ==================================================================
    const appLayout = document.querySelector('.app-layout');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileOverlay = document.getElementById('mobile-overlay');
    const SIDEBAR_STATE_KEY = 'sidebarCollapsed'; // Chave para salvar no localStorage

    // --- 1. Funções de Ação ---

    // Salva a preferência (colapsado/expandido) no localStorage
    function saveSidebarState(isCollapsed) {
        // Só salva a preferência em telas maiores (não-mobile)
        if (window.innerWidth > 767.98) {
            localStorage.setItem(SIDEBAR_STATE_KEY, isCollapsed ? 'true' : 'false');
        }
    }

    // Aplica a classe CSS no layout
    function applySidebarState(isCollapsed) {
         appLayout.classList.toggle('sidebar-collapsed', isCollapsed);
    }

    // Move o botão de toggle (hamburger no mobile, "pin" no desktop)
    function moveSidebarToggle() {
        const sidebarHeader = document.querySelector('.sidebar-header');
        const mainContent = document.querySelector('.main-content');
        if (!mainContent || !sidebarToggle || !sidebarHeader) return;
        
        let header = document.getElementById('main-content-header');
        
        if (window.innerWidth <= 767.98) {
            // MODO MOBILE: Move o botão para o header do conteúdo
            if (!header) {
                header = document.createElement('div');
                header.className = 'main-content-header';
                header.id = 'main-content-header';
                
                const h1 = mainContent.querySelector('div.mb-6 h1.text-2xl');
                if (h1) {
                    header.appendChild(h1);
                }
                header.appendChild(sidebarToggle);
                mainContent.prepend(header);
            }
        } else {
            // MODO TABLET/DESKTOP: Devolve o botão para a sidebar
            if (header) {
                const h1 = header.querySelector('h1.text-2xl');
                if (h1) {
                    const divMb6 = mainContent.querySelector('div.mb-6');
                    if(divMb6) {
                        divMb6.prepend(h1);
                    } else {
                         mainContent.prepend(h1);
                    }
                }
                header.remove(); 
            }
            appLayout.appendChild(sidebarToggle);
        }
    }

    // --- 2. Função Principal de Decisão (ao carregar e redimensionar) ---
    // ... (início do app.js)

    // --- 2. Função Principal de Decisão (ao carregar e redimensionar) ---
    function handleLayout() {
        const isMobile = window.innerWidth <= 767.98;
        
        moveSidebarToggle(); // 1. Sempre move o botão para o lugar certo

        if (isMobile) {
            // No MOBILE, está sempre escondido (colapsado = true)
            applySidebarState(true); 
            return; 
        }

        // ✅ CORRIGIDO: Em TABLET e DESKTOP (qualquer tela > 767.98px), 
        // a lógica agora respeita o localStorage.
        
        const savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
        
        // Se 'savedState' for 'true', aplica true.
        // Se for 'false' ou null (primeira visita), aplica false (expandido).
        applySidebarState(savedState === 'true');
    }

// ... (resto do app.js)

    // --- 3. Event Listeners ---
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const isCurrentlyCollapsed = appLayout.classList.contains('sidebar-collapsed');
            const newState = !isCurrentlyCollapsed;
            
            applySidebarState(newState); // Aplica a classe
            saveSidebarState(newState);  // Salva a preferência
        });
    }

    if (mobileOverlay) {
        // Fecha o menu mobile se clicar fora (no overlay)
        mobileOverlay.addEventListener('click', function() {
            applySidebarState(true); 
        });
    }

    // Listener de Redimensionamento (para trocar de mobile para desktop)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleLayout, 100); 
    });
    
    // --- 4. Execução Inicial ---
    handleLayout(); // Roda a lógica 1x no carregamento da página

}); // Fim do DOMContentLoaded