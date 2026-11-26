# Proxmox VE Dashboard

Tema WordPress que transforma a página inicial do seu site em um **mapa da infraestrutura homelab**, com dados estáticos gerenciados pelo WP-Admin e métricas lidas em tempo real da API oficial do **Proxmox VE**.

**Versão:** 1.0.0 · **WordPress:** 6.0+ (testado até 6.8) · **PHP:** 7.4+

---

## Funcionalidades

- **Mapa da infraestrutura:** topologia visual `INTERNET → ROTEADOR → SWITCH → REDE LAN`, com rótulos e IPs editáveis no painel.
- **Cartão do servidor:** IP, sistema operacional, função, MAC, URL e mensagens de status, com logotipo personalizado (aceita SVG e PNG).
- **Containers LXC e serviços Docker:** cards configuráveis com ícone, IP, MAC, domínio, porta e função. Clique em um card para copiar suas informações.
- **Métricas globais via API:** CPU (média do cluster), RAM, armazenamento, quantidade de nodes/storages e uptime.
- **Armazenamento:** cards lidos automaticamente do Proxmox ou uma lista personalizada definida no painel.
- **Tabelas de monitoramento:** nodes, máquinas virtuais (QEMU), containers (LXC) e storages, com barras de uso e badges de status.
- **Atualização automática:** intervalo configurável em segundos (`0` desativa o auto-refresh) e relógio em tempo real no rodapé.
- **Painel administrativo completo:** 4 abas para conexão, personalização, conteúdo e backup, com botão de teste de conectividade.
- **Importar/exportar:** backup de todas as configurações em JSON, com opções para incluir o Token Secret e preservar as credenciais locais na restauração.
- **Responsivo:** layout adaptado para desktop, tablet e celular.

## Instalação

1. Copie a pasta `proxmox-theme` para `wp-content/themes/` da sua instalação WordPress.  
   Também é possível compactar a pasta em `.zip` e enviar em **Aparência → Temas → Adicionar novo → Enviar tema**.
2. Ative o tema em **Aparência → Temas**.
3. Acesse **Proxmox VE** no menu lateral do WP-Admin e configure a conexão com a API.
4. Defina a página inicial do site (a renderização do dashboard usa `front-page.php`).
5. Visite a home do site para ver o dashboard.

## Configurando a API do Proxmox

1. No painel do Proxmox VE, vá em **Datacenter → Access → API Tokens** e clique em **Add**.
2. Selecione o usuário (por exemplo, `root@pam` ou um usuário exclusivo `dashboard@pve`) e defina um **Token ID** (por exemplo, `api`).
3. Para dar ao token apenas permissões de leitura, use um usuário com uma role somente-leitura, como `PVEAuditor`, com acesso a `/nodes`, `/cluster/resources` e `/version`. Se o token precisar herdar as permissões do usuário, deixe **Privilege Separation** desativado.
4. Copie os valores gerados:
   - **Token ID/identifier:** `usuario@realm!tokenid` (ex.: `dashboard@pve!api`)
   - **Token Secret:** o valor exibido uma única vez na criação
5. No WordPress, em **Proxmox VE → Conexão API**, preencha:
   - **URL da API Proxmox:** `https://192.168.2.50:8006` (ou o domínio do seu Proxmox)
   - **Token ID** e **Token Secret**
   - **Intervalo de atualização** em segundos (`0` desativa)
   - **Verificar SSL:** desmarque se o Proxmox usar certificado self-signed
6. Clique em **Testar conexão com API** e salve as configurações.

O Token Secret fica salvo apenas no banco do WordPress e **não é enviado para o navegador**; o frontend consome os endpoints REST do próprio WordPress.

## Painel administrativo

O menu **Proxmox VE** possui quatro abas:

| Aba | O que configura |
| --- | --- |
| **Conexão API** | URL do Proxmox, Token ID, Token Secret, intervalo de refresh, verificação de SSL e teste de conectividade. |
| **Personalização** | Nome/título/subtítulo da marca, texto do rodapé, ícone e dados do servidor, rótulos do roteador/switch e rede LAN. |
| **Conteúdo** | Listas repetíveis de containers LXC, serviços Docker e armazenamento personalizado (opcional). |
| **Importar/Exportar** | Download e restauração das configurações em JSON, com controle sobre credenciais exportadas e preservadas. |

Na aba **Conteúdo**, os ícones aceitam URLs de imagem/SVG, e o botão **Selecionar** abre a biblioteca de mídia do WordPress. O envio de arquivos SVG está habilitado pelo tema.

## Endpoints REST

O tema registra endpoints somente de leitura sob o namespace `pvd/v1`:

| Método | Endpoint | Retorno |
| --- | --- | --- |
| `GET` | `/wp-json/pvd/v1/status` | Versão, nodes, storages, CPU/RAM/disco e uptime |
| `GET` | `/wp-json/pvd/v1/nodes` | Nodes do cluster |
| `GET` | `/wp-json/pvd/v1/resources` | VMs (QEMU) e LXC do cluster |
| `GET` | `/wp-json/pvd/v1/vms` | Máquinas virtuais QEMU |
| `GET` | `/wp-json/pvd/v1/lxc` | Containers LXC |
| `GET` | `/wp-json/pvd/v1/storage` | Storages com uso, livre e total |

> **Atenção:** esses endpoints são públicos (`permission_callback` aberto), pois o dashboard pode ser exibido sem login. Não publique o site sem proteção de acesso se os dados da sua infraestrutura precisam ser privados. O token do Proxmox nunca aparece nas respostas.

Sem a API configurada, o dashboard exibe um aviso e mantém apenas os cards e textos definidos no painel.

## Estrutura de arquivos

```text
proxmox-theme/
├── assets/
│   ├── css/
│   │   ├── admin.css        # Estilos do painel administrativo
│   │   └── dashboard.css    # Estilos do dashboard
│   └── js/
│       ├── admin.js         # Repeater fields e seletor de mídia
│       └── dashboard.js     # Renderização, polling, relógio e clipboard
├── img/                     # Ícones SVG dos serviços e do Proxmox
├── includes/
│   ├── helpers.php          # Opções padrão, sanitização e getters
│   ├── proxmox-api.php      # Cliente da API REST do Proxmox VE
│   ├── rest-api.php         # Endpoints REST do WordPress
│   └── settings.php         # Menu, abas e importação/exportação
├── footer.php
├── front-page.php           # Template do dashboard
├── functions.php            # Bootstrap do tema e enqueue de assets
├── header.php
├── index.php
├── screenshot.png
├── style.css                # Cabeçalho do tema
└── README.md
```

## Licença

Distribuído sob a licença MIT.
