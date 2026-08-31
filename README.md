# Proxmox Dashboard (Tema WordPress)

Tema WordPress em tela única (full-screen) que transforma o WordPress em um painel moderno de monitoramento do **Proxmox VE**, utilizando a API REST oficial do Proxmox.

O painel exibe, em tempo real: status geral, nodes, máquinas virtuais (VMs/QEMU), containers (LXC), utilização de CPU, memória, disco, lista de storages e **Serviços Monitorados** (health-check HTTP de sistemas que rodam nas VMs, ex: Gitea).

**Versão:** 2.2.0 · **Requer PHP:** 8.1+ · **Testado até:** WordPress 6.7

---

## 📋 Índice

1. [Funcionalidades](#-funcionalidades)
2. [Requisitos](#-requisitos)
3. [Instalação](#-instalação)
4. [Configuração no WordPress](#-configuração-no-wordpress)
5. [Criando o Token no Proxmox](#-criando-o-token-no-proxmox)
6. [Serviços Monitorados (VMs)](#-serviços-monitorados-vms)
7. [Modo TV e Tela Cheia](#-modo-tv-e-tela-cheia)
8. [Estrutura do tema](#-estrutura-do-tema)
9. [API do WordPress (endpoints internos)](#-api-do-wordpress-endpoints-internos)
10. [Solução de problemas](#-solução-de-problemas)
11. [Segurança](#-segurança)
12. [Licença](#-licença)

---

## ✅ Funcionalidades

- Painel **full-screen** sem sidebar/widgets — visual limpo e focado na operação.
- **Atualização automática** em intervalos configuráveis (manual, 1s a 1h + personalizado 1-86400s).
- Cards de resumo: número de **nodes**, **VMs ativas**, **LXC ativos** e **storages**.
- Medidores (gauges) de **CPU**, **Memória** e **Disco**.
- Tabelas detalhadas de **nodes**, **VMs**, **containers LXC** e **storages**, com badges de status, barras de uso e uptime.
- **Serviços Monitorados** — cadastro manual e gradativo de URLs internas (ex: `http://192.168.2.100:3001/` Gitea) com ícone Font Awesome/emoji/URL, exibidos como cards com status **Online/Offline**, `HTTP code` e latência `ms` (health-check server-side sem CORS).
- **Modo TV (sem rolagem)** — grade 2×2 + auto-scale para exibir tudo em 100vh sem scrollbar (ideal para TV/Tablet quiosque, `?kiosk=1`).
- **Tela cheia** nativa (Fullscreen API) com fallback para iframe/HTTP, adaptada para **1007×768** e **1903×927**.
- Header **só-ícones** (clean) — Modo TV / Tela cheia / Atualizar em 38×38px com estados `is-active` e toast de feedback.
- Layout refinado v3 — em desktop ≥1100px as 4 tabelas já ficam em **grade 2×2** (50% menos scroll); `1007×768` e `1903×927` com gaps/gauges compactados.
- **Favicon** automático via `img/Proxmox-Logo.svg/.png` quando não há Site Icon no Customizer.
- O **token da API nunca chega ao navegador** — toda a comunicação com o Proxmox ocorre no servidor.
- Página de administração com **teste de conexão** integrado e box verde de instruções com alerta de **Privilege Separation**.

---

## 📌 Requisitos

- WordPress 6.0+ (testado até 6.7)
- PHP 8.1 ou superior
- Proxmox VE com API REST habilitada (padrão) e um **API Token** — ver [Criando o Token](#-criando-o-token-no-proxmox)

---

## 🚀 Instalação

1. Copie a pasta `proxmox-dashboard` para `wp-content/themes/` (ou envie o `.zip` via **Aparência → Temas → Adicionar novo → Enviar tema**).
2. Ative o tema em **Aparência → Temas**.
3. Configure a conexão — ver [Configuração no WordPress](#-configuração-no-wordpress).
4. Clique em **Salvar configurações** e depois em **Testar conexão**.
5. Acesse a página inicial do site para ver o painel. Limpe o cache (`Ctrl+F5`) após atualizar para `2.2.0` (`?ver=2.2.0`).

---

## ⚙️ Configuração no WordPress

Acesse o menu **Proxmox Dashboard → Configurações** e preencha:

| Campo | Descrição | Exemplo |
|---|---|---|
| **Proxmox Host** | URL completa do Proxmox, incluindo a porta (8006) ou domínio. | `https://192.168.2.50:8006` |
| **API User** | Usuário no formato `nome@realm`. | `dashboard@pve` |
| **API Token ID** | ID do token (sem o prefixo `usuário@realm!`). | `dashboard` |
| **API Token Secret** | Secret gerado pelo Proxmox (mostrado apenas 1 vez). | `a89b39be-…` |
| **Intervalo de atualização** | Frequência de atualização do dashboard (manual a 1h + personalizado). | `5 segundos` |
| **Serviços Monitorados** | Lista manual: Nome \| URL \| Ícone FA (ex: `fa-brands fa-git-alt`). | `Gitea \| http://192.168.2.100:3001/ \| fa-brands fa-git-alt` |

> O **token Secret** fica armazenado apenas no servidor e nunca é exibido no navegador (na página de configuração aparece mascarado como `******`).
> URLs sem esquema (`192.168.2.100:3001`) são auto-corrigidas para `http://`.

O tema monta a autenticação como: `PVEAPIToken={usuário}!{token_id}={secret}` (ex.: `PVEAPIToken=dashboard@pve!dashboard=a89b39be-…`).

---

## 🔑 Criando o Token no Proxmox

Para gerar o token necessário ao tema:

1. No painel do Proxmox, acesse **Datacenter → Access → API Tokens**.
2. Clique em **Add**.
3. Preencha os campos:
   - **User:** usuário de acesso (ex.: `dashboard@pve` ou `root@pam`).
   - **Token ID:** um identificador (ex.: `dashboard`).
   - **Privilege Separation:** ver nota abaixo (deixe **OFF/desmarcado**).
   - **Expire:** defina como `never` ou um prazo desejado.
4. Copie e guarde o **Secret** exibido (não é possível vê-lo novamente).
5. Em **Datacenter → Permissions** atribua `PVEAuditor` (leitura) ou `PVEAdmin` ao usuário/grupo.
6. Preencha esses dados no WordPress (ver [Configuração](#-configuração-no-wordpress)).

### ✅ Importante sobre "Privilege Separation" (box verde no admin)

- **Desativado / OFF** (recomendado): o token **herda** os privilégios do usuário. Para `root@pam`, o token vê todos os dados (VMs, LXC, storage).
- **Ativado / ON**: o token **não herda** os privilégios — ele passa a ter apenas as permissões atribuídas explicitamente a ele. **Sem ACLs adequadas, o dashboard mostrará dados zerados** (VMs = 0, LXC = 0, storage = 0).

> Se o painel mostrar apenas o node online e tudo mais zerado, verifique o box verde em **Proxmox Dashboard → Configurações**: `Datacenter → Access → API Tokens → Add → Privilege Separation → OFF`.

---

## 🌐 Serviços Monitorados (VMs)

Cadastro manual e gradativo em **Configurações → Serviços Monitorados**:

- **Nome:** ex: `Gitea`
- **URL:** ex: `http://192.168.2.100:3001/` (internas `192.168.x` permitidas, timeout 5s)
- **Ícone:** classe Font Awesome (`fa-brands fa-git-alt`, `fa-solid fa-server`), URL de imagem ou emoji. Deixe vazio para ícone padrão. Biblioteca: [fontawesome.com/search?o=r&m=free](https://fontawesome.com/search?o=r&m=free) (FA 6.5.2 já enfileirado via CDN).

No dashboard aparece entre **Utilização** e **Nodes** como cards clicáveis (`target="_blank"`), com badge `Online/Offline`, `HTTP code` e latência. Fallback PHP renderiza `Verificando…` imediatamente; JS atualiza no mesmo intervalo do painel. Se vazio, mostra mensagem orientativa (sem quebrar layout).

---

## 📺 Modo TV e Tela Cheia

Header com 3 botões só-ícone (38px):

| Botão | Ação | Atalho |
|---|---|---|
| **Modo TV** | `body.pxd-kiosk` → `100vh/100dvh`, `scrollbar:none`, grade services + tabelas 2×2, auto-scale `0.55–1` para caber sem rolagem. Persiste em `localStorage` e `?kiosk=1` (ideal para TV que abre URL fixa). | Clique / `ESC` sai |
| **Tela cheia** | Fullscreen API em `#proxmox-dashboard-page` + fallback `pxd-is-fullscreen-fallback` para iframe/HTTP. | Clique / `F11` / `ESC` |
| **Atualizar** | `fetch` manual | Clique |

Ambos exibem toast inferior (“Modo TV ativado — sem rolagem”). Layouts otimizados: `1007×768` (header só ícones, gauges 92px, services 4 colunas) e `1903×927` (max-width 1820px, gauges 128px, services `minmax 240px`).

---

## 📁 Estrutura do tema

```
proxmox-dashboard/
│
├── style.css            → Metadados do tema (2.2.0)
├── functions.php        → Setup, enqueue (dashboard.css/js + Font Awesome), favicon, opções
├── index.php            → Template de fallback
├── front-page.php       → Dashboard full-screen (header + summary/usage/services/tables-grid)
├── header.php           → <head> + favicon (img/Proxmox-Logo.svg/.png) + #proxmox-dashboard-page
├── footer.php           → Rodapé
│
├── assets/
│   ├── css/dashboard.css → Estilos (cards, gauges, tabelas, services, kiosk, fullscreen, responsivo)
│   └── js/dashboard.js  → Fetch REST + render (summary/usage/nodes/vms/lxc/storage/services) + fullscreen/kiosk
│
├── includes/
│   ├── helpers.php      → Formatação, proxmox_dashboard_get_options/services
│   ├── proxmox-api.php  → Cliente REST Proxmox (singleton, PVEAPIToken, sslverify filter)
│   ├── rest-api.php     → Endpoints /status /nodes /resources /vms /lxc /storage /services
│   └── settings.php     → Admin (conexão + intervalo + serviços + teste + box instruções verde)
│
├── img/
│   ├── Proxmox-Logo.svg
│   └── Proxmox-Logo.png
│
├── LICENSE
└── README.md
```

> **Nota:** todos os arquivos devem estar **exatamente** nesta estrutura. Se algum arquivo de `includes/` ou `assets/` ficar ausente, o tema gera **erro crítico**. Veja [Solução de problemas](#-solução-de-problemas).

---

## 🔗 API do WordPress (endpoints internos)

O tema registra os seguintes endpoints REST (consumidos pelo JavaScript):

| Endpoint | Descrição |
|---|---|
| `/wp-json/proxmox/v1/status` | Status geral (versão, nodes, storage, uso agregado) |
| `/wp-json/proxmox/v1/nodes` | Lista de nodes |
| `/wp-json/proxmox/v1/resources` | VMs + containers (recursos) |
| `/wp-json/proxmox/v1/vms` | Apenas VMs (QEMU) |
| `/wp-json/proxmox/v1/lxc` | Apenas containers LXC |
| `/wp-json/proxmox/v1/storage` | Lista de storages |
| `/wp-json/proxmox/v1/services` | Health-check dos Serviços Monitorados (server-side, `wp_remote_get` 5s, sem CORS) |

O **token do Proxmox nunca é exposto ao navegador**: toda a comunicação é feita em `includes/proxmox-api.php`. O tema usa nonce `wp_rest`.

---

## 🛠 Solução de problemas

### "Ocorreu um erro crítico neste site" (erro fatal)

- Confirme que `includes/helpers.php`, `includes/proxmox-api.php`, `includes/rest-api.php` e `includes/settings.php` existem.
- Verifique `debug.log` (ex.: `require_once(): Failed opening required …/includes/helpers.php`).

### Dashboard mostra apenas o node, mas VMs/LXC/Storage zerados

- **Causa:** token com **Privilege Separation ativado** sem ACLs.
- **Solução:** recriar token com **Privilege Separation OFF** ou atribuir `PVEAuditor` em `Datacenter → Permissions`. Ver box verde no admin.

### "Falha na conexão" ao testar

- Verifique host, usuário, token ID e secret (sem espaços).
- Se Proxmox usa certificado autoassinado, ajuste `add_filter('proxmox_dashboard_sslverify','__return_false')` em tema filho ou use cert válido.
- Confirme que o WordPress consegue alcançar o host na rede.

### Modo TV / Tela cheia parece sem ação

- Após atualizar para `2.2.0`, faça `Ctrl+F5`. `init()` agora sempre liga os botões antes do `loadAll`; clique deve mostrar toast e botão ficar azul (`is-active`). Se não, veja `F12 → Console` e `Network → dashboard.js?ver=2.2.0`.

### Serviços Monitorados só mostra título ou sem CSS

- `Ctrl+F5` + purge de cache (LiteSpeed/WP Rocket/Cloudflare). Confirme em `F12 → Network` que `dashboard.css?ver=2.2.0` contém `.pxd-service-card`.
- Verifique se a URL foi salva com `http://` (o sanitizador agora corrige `192.168.x:porta → http://`).
- Se grid ficar vazio, JS mostrou `Nenhum serviço cadastrado` — adicione em **Configurações → Serviços Monitorados**.

### Cards de Serviços sem ícone

- Use classe FA válida (`fa-solid fa-server`) ou URL/emoji. FA 6.5.2 é carregado via `cdnjs.cloudflare.com` em `functions.php:54`.

---

## 🔒 Segurança

- Prefira um **API Token dedicado com a menor permissão possível** (`PVEAuditor`) a `root@pam`.
- **Nunca** exponha o token em JS/HTML/URLs/repositórios.
- Token armazenado apenas nas opções do WordPress (servidor).
- Use **HTTPS** e mantenha WordPress/PHP/Proxmox atualizados.

---

## 📄 Licença

MIT — consulte o arquivo [`LICENSE`](LICENSE).
