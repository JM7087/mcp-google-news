# MCP Google News — servidor MCP em PHP

Um servidor MCP (Model Context Protocol) em PHP que expõe ferramentas úteis para buscar notícias, obter data/hora e consultar cotações.

**Melhorias aplicadas:** caminhos corrigidos, Quick Start, exemplos JSON-RPC, instruções de debug e diagrama de projeto atualizado.

## Quick Start

Pré-requisitos mínimos:

- PHP 8.1 ou superior
- Extensões recomendadas: `mbstring`, `simplexml`, `json`
- Acesso à internet para consumir APIs externas

Executar localmente (PowerShell / CMD):

```powershell
cd c:\mcp-google-news\mcp-google-news
php mcpGoogleNews.php
```

Ou em Bash / macOS / Linux:

```bash
cd ~/mcp-google-news/mcp-google-news
php mcpGoogleNews.php
```

## Ferramentas disponíveis

- `get_news` — busca notícias do Google News via RSS
    - Parâmetros: `query` (opcional), `limit` (default 10, max 50), `language` (ex: `pt-BR`)
- `get_datetime` — retorna data e hora do sistema
    - Parâmetros: `timezone` (ex: `America/Sao_Paulo`), `format` (`completo`, `data`, `hora`, `iso8601`)
- `get_exchange_rate` — obtém cotações via Frankfurter (fallbacks implementados)
    - Parâmetros: `from` (ex: `USD`), `to` (ex: `BRL,EUR`), `amount`

## Exemplo JSON-RPC (requisição)

Requisição para inicializar e chamar `get_datetime` (uma linha JSON por vez):

```json
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}
```

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_datetime","arguments":{}}}
```

Exemplo de resposta esperada (abreviado):

```json
{"jsonrpc":"2.0","id":2,"result":{"timezone":"America/Sao_Paulo","data":"17/06/2026","hora":"12:34:56","iso8601":"2026-06-17T12:34:56-03:00"}}
```

## Habilitar debug

Para ativar logs detalhados, abra `mcpGoogleNews.php` e defina a variável `$ativarDebug = true;` no topo do arquivo. O servidor escreve `mcp_debug.log` no mesmo diretório.

## Configuração do MCP (exemplo `mcp.json`)

Exemplo simples que funciona neste repositório:

```json
{
    "servers": {
        "mcp-google-news": {
            "command": "php",
            "args": ["mcpGoogleNews.php"],
            "cwd": "c:\\mcp-google-news\\mcp-google-news"
        }
    }
}
```

> Se usar LM Studio ou outra interface, ajuste `command`/`args` conforme necessário.

## Estrutura do projeto

```
mcp-google-news/
├── composer.json
├── mcpGoogleNews.php    # Servidor MCP
├── README.md
├── LICENSE
└── mcp.json (opcional)
```

## Troubleshooting rápido

- Erro de parse JSON: verifique que cada requisição seja enviada como uma única linha JSON.
- Erros de conexão: confirme acesso à internet e se APIs externas não estão bloqueadas.
- Permissões de arquivo: verifique se o processo PHP tem permissão de escrita para criar `mcp_debug.log`.

## APIs utilizadas

- **Google News RSS**: Feed público de notícias
- **Frankfurter API**: API gratuita de cotação de moedas (https://frankfurter.app)
- **Exchange Rate API**: API alternativa de cotação (https://open.er-api.com)

## ⚠️ Limitações

- Google News pode ter limite de requisições
- API de cotação não inclui criptomoedas
- Requer conexão com internet

## Contribuição

Contribuições são bem-vindas! Se você encontrar algum problema ou tiver alguma sugestão de melhoria, por favor, abra uma issue ou envie um pull request.

## Créditos

- Desenvolvido por [João Marcos](https://links.jm7087.com/)

## Licença

Este projeto é licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para mais detalhes.
