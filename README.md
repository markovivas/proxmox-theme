# Proxmox Dashboard & Mapa da Infraestrutura (Homelab)

Tema WordPress que transforma seu site em um **Mapa de Infraestrutura Homelab** moderno, elegante e interativo, integrado em tempo real com a API oficial do **Proxmox VE**.

Apresenta topologia de rede, nós de virtualização, containers LXC, máquinas virtuais, serviços Docker expostos com Cloudflare, discos de armazenamento físico e medidores ao vivo de CPU, RAM e Disco.

**Versão:** 2.4.0 · **Requer PHP:** 8.1+ · **Testado até:** WordPress 6.7

---

## 📋 Índice

1. [Funcionalidades](#-funcionalidades)
2. [O que há de novo na v2.4.0](#-o-que-há-de-novo-na-v240)
3. [Instalação](#-instalação)
4. [Como Preencher e Gerenciar pelo Painel](#-como-preencher-e-gerenciar-pelo-painel)
5. [Criando o Token no Proxmox (3 Opções)](#-criando-o-token-no-proxmox-3-opções)
6. [Estrutura de Arquivos](#-estrutura-de-arquivos)
7. [Licença](#-licença)

---

## ✅ Funcionalidades

- **Identidade Visual Homelab & Proxmox:** Layout limpo e moderno com gradientes sutis, tipografia Inter e marca estilizada do Proxmox.
- **Topologia de Rede:** Diagrama visual do fluxo de rede: `INTERNET ➔ ROTEADOR ➔ SWITCH ➔ REDE LAN`.
- **Cartão do Servidor:** Exibe IP, SO, Função, MAC e URL externa com status de ambiente.
- **Métricas Globais em Tempo Real:** Gauges dinâmicos de CPU (pico do cluster), Memória RAM consumida e Disco total direto da API Proxmox.
- **Containers (LXC) & VMs com Status Live:** Cards com ID badge, logos/imagens personalizados, IP local, endereço MAC, domínio Cloudflare, função/descrição e **pulso de status ao vivo** (online/offline) conectado à API do Proxmox pelo VMID.
- **Serviços Docker:** Grid de serviços com portas, domínios externos e **health-check HTTP server-side em tempo real** (latência em ms e status online/offline).
- **Armazenamento & Legenda:** Mapeamento visual de discos físicos (SSD, HDD, NVMe) e legenda com tags coloridas (`LXC`, `IP`, `URL`, `VM`, `MAC`, `Porta`).
- **Backup & Migração (Importação/Exportação):** Exporte e importe todas as configurações e cadastros em arquivo JSON com 1 clique.
- **Upload de Imagens Nativo:** Botão "Selecionar Imagem" com integração direta à Biblioteca de Mídia do WordPress (`wp.media`) para subir SVGs, PNGs e ícones facilmente.
- **Clique para Copiar:** Clique em qualquer card de container ou serviço para copiar dados diretamente com feedback visual e toast.
- **Alternador de Visualização:** Alterne entre o **Mapa da Infraestrutura** e as **Métricas & Tabelas Técnicas** com 1 clique.
- **Modo TV (Kiosk) & Tela Cheia:** Ideal para tablets na mesa ou monitores de parede 24/7.
- **Relógio em Tempo Real:** Relógio digital preciso no rodapé.

---

## 🚀 O que há de novo na v2.4.0

1. **Importação e Exportação de Configurações (Backup Completo):** Nova aba dedicada que permite baixar um arquivo JSON com todas as configurações do dashboard (topologia, containers LXC, VMs, serviços Docker, discos e legenda) e restaurá-las com 1 clique.
2. **Proteção e Preservação de Credenciais:** Opção para incluir ou omitir o token secret no arquivo exportado e opção de preservar as credenciais Proxmox locais durante a restauração.
3. **Importação Flexível:** Suporta envio de arquivo `.json` ou colagem direta do código JSON no painel.
4. **Tema "Zerado" e 100% Gerenciável:** Todas as informações do servidor, topologia, containers, serviços docker, discos e legenda podem ser preenchidas, editadas ou excluídas pelo WP-Admin.

---

## 🛠️ Instalação

1. Copie a pasta `proxmox-theme` para `wp-content/themes/` da sua instalação WordPress (ou compacte em `.zip` e envie via **Aparência → Temas → Adicionar Novo → Enviar tema**).
2. Ative o tema em **Aparência → Temas**.
3. Acesse **Proxmox Dashboard → Configurações** no menu lateral do WordPress.
4. Preencha a conexão e adicione seus containers e serviços.
5. Acesse a página inicial do seu site.

---

## ⚙️ Como Preencher e Gerenciar pelo Painel

Acesse **Proxmox Dashboard → Configurações** no menu do WordPress. O painel é dividido em 6 abas:

### 1. 🔑 Conexão Proxmox
- **Proxmox Host:** Ex.: `https://proxmox.marcovivas.online` ou `https://192.168.2.50:8006`
- **Token ID:** Ex.: `root@pam!api` ou `dashboard@pve!api`
- **API Token Secret:** Ex.: `e7911894-268d-4624-a6df-686d0a504be8`
- **Intervalo de Atualização:** 1s, 5s, 10s, 30s, 1 min, ou personalizado.
- **Testar Conexão:** Botão para testar a comunicação imediata com o Proxmox.

### 2. 🖥️ Topologia & Servidor
- **Dados do Servidor:** IP Local (`192.168.2.50`), SO (`Proxmox VE 9.2.20`), Função (`Virtualização`), MAC (`f0:4d:a2:e6:64:5e`), URL (`proxmox.marcovivas.online`), Mensagem de status e upload do Logo/Ícone.
- **Diagrama de Rede:** IP do Roteador/Gateway (`192.168.2.1`), Rótulo do Switch e Sub-rede LAN (`192.168.2.0/24`).

### 3. 📦 Containers (LXC) & VMs
- Clique em **Adicionar Container / VM** para criar quantos desejar.
- **Tipo:** LXC ou VM (QEMU).
- **ID (VMID):** Número do container no Proxmox (ex.: `100`). *Se coincidir com o Proxmox, o tema conecta o status online/offline e medidor de RAM automaticamente!*
- **Ícone / Imagem:** Clique em `📁` para escolher qualquer imagem ou SVG da biblioteca do WordPress.
- **IP Local, MAC Address, URL e Função/Descrição.**
- Botão **Excluir** para remover qualquer card a qualquer momento.

### 4. 🐳 Serviços Docker
- Clique em **Adicionar Serviço Docker** para registrar os serviços (ex.: Portainer, Gitea, Jellyfin).
- **Nome, Ícone/Imagem, Porta no Host** (ex.: `9443`), **Domínio Externo** (ex.: `portainer.marcovivas.online`).
- **URL de Health-Check:** (Opcional) URL para ping HTTP automático com medição de latência em milissegundos e status Online/Offline.

### 5. 💾 Armazenamento & Legenda
- **Discos de Armazenamento:** Adicione, edite ou exclua unidades (SSD, HDD, NVMe) com título e lista de alocações.
- **Itens da Legenda:** Adicione ou edite tags personalizadas (`LXC`, `IP`, `URL`, `VM`, `MAC`, `Porta`) com cores e descrições.

### 6. 🔄 Importar / Exportar (Backup & Migração)
- **Exportar Configurações:** Baixe um arquivo `.json` completo com todas as definições da infraestrutura para backup ou migração para outro servidor. Inclui opção de exportar com ou sem o Secret da API.
- **Importar Configurações:** Restaure seu dashboard enviando o arquivo `.json` ou colando o texto JSON diretamente. Inclui opção de preservar as credenciais de conexão do Proxmox local.

---

## 🔑 Criando o Token no Proxmox (3 Opções)

1. No painel do Proxmox VE, acesse **Datacenter → Access → API Tokens**.
2. Clique em **Add**.
3. Selecione o **User** (ex.: `root@pam` ou crie um usuário `dashboard@pve`), defina o **Token ID** (ex.: `api`) e **desmarque** a opção `Privilege Separation` (deixe **OFF**).
4. Na janela que surgir ("Token Secret"), você verá:
   - **Token ID:** `root@pam!api`
   - **Secret:** `e7911894-268d-4624-a6df-686d0a504be8`
5. Cole exatamente esses dados no WordPress junto com o seu **Host**.

---

## 📁 Estrutura de Arquivos

```
proxmox-theme/
├── assets/
│   ├── css/
│   │   └── dashboard.css   # Estilos reformulados unificando proxmox_html
│   └── js/
│       └── dashboard.js    # Lógica reativa, relógio, clipboard e polling
├── img/
│   ├── Proxmox-Logo.png
│   └── Proxmox-Logo.svg
├── includes/
│   ├── helpers.php         # Getters, normalização e defaults
│   ├── proxmox-api.php     # Cliente REST oficial do Proxmox VE
│   ├── rest-api.php        # Endpoints REST e health-check do WordPress
│   └── settings.php        # Painel administrativo com abas e upload de mídia
├── footer.php
├── front-page.php          # Visual frontal completo do Homelab
├── functions.php           # Enqueue de fontes, scripts e wp.media
├── header.php
├── index.php
├── LICENSE
├── README.md
└── style.css
```

---

## 📄 Licença

Distribuído sob licença GNU General Public License v2 ou superior.
