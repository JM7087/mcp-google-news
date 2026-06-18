<?php
/**
 * MCP Server em PHP
 * Funcionalidades:
 * - Buscar noticias do Google News
 * - Obter data e hora do sistema
 * - Cotacao de moedas (API gratuita)
 */

declare(strict_types=1);

// Forcar UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Definir timezone padrao
date_default_timezone_set('America/Sao_Paulo');

// Desabilitar output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Configurar streams para UTF-8 no Windows
if (PHP_OS_FAMILY === 'Windows') {
    // Tentar configurar console para UTF-8
    @exec('chcp 65001 > nul 2>&1');
}

// Log para debug (escreve em arquivo)

$ativarDebug = false; // Mudar para true para ativar logs detalhados
function debugLog(string $message): void {
    global $ativarDebug;
    if ($ativarDebug) {
        $logFile = __DIR__ . '/mcp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

/**
 * Classe principal do MCP Server
 */
class McpServer {
    private const SERVER_NAME = "mcp-noticia-php";
    private const SERVER_VERSION = "1.0.0";
    
    private array $tools = [];
    
    public function __construct() {
        $this->registerTools();
    }
    
    /**
     * Registra todas as ferramentas disponiveis
     */
    private function registerTools(): void {
        $this->tools = [
            'get_news' => [
                'name' => 'get_news',
                'description' => 'Busca as ultimas noticias do Google News. Pode filtrar por termo de busca.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Termo de busca para filtrar noticias (opcional). Ex: "tecnologia", "brasil", "economia"'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Numero maximo de noticias a retornar (padrao: 10, maximo: 50)',
                            'default' => 10
                        ],
                        'language' => [
                            'type' => 'string',
                            'description' => 'Codigo do idioma (padrao: pt-BR). Ex: "pt-BR", "en-US"',
                            'default' => 'pt-BR'
                        ]
                    ],
                    'required' => []
                ]
            ],
            'get_datetime' => [
                'name' => 'get_datetime',
                'description' => 'Retorna a data e hora atual do sistema com informacoes detalhadas.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone para a data/hora (padrao: America/Sao_Paulo). Ex: "UTC", "America/New_York"',
                            'default' => 'America/Sao_Paulo'
                        ],
                        'format' => [
                            'type' => 'string',
                            'description' => 'Formato da data (padrao: completo). Opcoes: "completo", "data", "hora", "iso8601"',
                            'default' => 'completo'
                        ]
                    ],
                    'required' => []
                ]
            ],
            'get_exchange_rate' => [
                'name' => 'get_exchange_rate',
                'description' => 'Obtem a cotacao atual ou historica de moedas usando API gratuita (Frankfurter API). Aceita `date` ou `start_date`/`end_date`.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => [
                            'type' => 'string',
                            'description' => 'Codigo da moeda de origem (padrao: USD). Ex: "USD", "EUR", "GBP"',
                            'default' => 'USD'
                        ],
                        'to' => [
                            'type' => 'string',
                            'description' => 'Codigo da moeda de destino (padrao: BRL). Pode ser multiplas separadas por virgula. Ex: "BRL", "EUR,GBP,JPY"',
                            'default' => 'BRL'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Valor a ser convertido (padrao: 1)',
                            'default' => 1
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Data historica (YYYY-MM-DD) para cotacao pontual (opcional)'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Data inicial para historico (YYYY-MM-DD) (opcional)'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Data final para historico (YYYY-MM-DD) (opcional)'
                        ]
                    ],
                    'required' => []
                ]
            ],
            'get_weather' => [
                'name' => 'get_weather',
                'description' => 'Obtem previsao do tempo atual, dos proximos dias ou historico usando Open-Meteo API (gratuita).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => [
                            'type' => 'string',
                            'description' => 'Nome da cidade para buscar o clima. Ex: "Sao Paulo", "Rio de Janeiro", "New York"'
                        ],
                        'latitude' => [
                            'type' => 'number',
                            'description' => 'Latitude da localizacao (opcional se informar a cidade). Ex: -23.55'
                        ],
                        'longitude' => [
                            'type' => 'number',
                            'description' => 'Longitude da localizacao (opcional se informar a cidade). Ex: -46.63'
                        ],
                        'mode' => [
                            'type' => 'string',
                            'description' => 'Modo de consulta: "current" (atual), "forecast" (previsao 7 dias), "history" (historico). Padrao: current',
                            'default' => 'current'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Data inicial para historico (formato: YYYY-MM-DD). Obrigatorio se mode=history'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Data final para historico (formato: YYYY-MM-DD). Obrigatorio se mode=history'
                        ]
                    ],
                    'required' => []
                ]
            ],
            'search_wikipedia' => [
                'name' => 'search_wikipedia',
                'description' => 'Busca artigos na Wikipedia. Pode pesquisar termos e retornar resumos ou artigos completos.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Termo de busca na Wikipedia. Ex: "Albert Einstein", "Brasil", "Inteligencia artificial"'
                        ],
                        'language' => [
                            'type' => 'string',
                            'description' => 'Codigo do idioma da Wikipedia (padrao: pt). Ex: "pt", "en", "es"',
                            'default' => 'pt'
                        ],
                        'mode' => [
                            'type' => 'string',
                            'description' => 'Modo de busca: "search" (lista resultados), "summary" (resumo do artigo), "full" (artigo completo). Padrao: summary',
                            'default' => 'summary'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Numero maximo de resultados na busca (padrao: 5, maximo: 20). Usado apenas no modo search.',
                            'default' => 5
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }
    
    /**
     * Executa o servidor MCP
     */
    public function run(): void {
        debugLog("MCP Server iniciado");
        
        // Configurar stream para não bloquear
        stream_set_blocking(STDIN, true);
        
        // Ler do stdin linha por linha
        while (true) {
            $line = fgets(STDIN);
            
            // Se fgets retornou false, verificar se é EOF
            if ($line === false) {
                if (feof(STDIN)) {
                    debugLog("EOF detectado, encerrando");
                    break;
                }
                // Pequena pausa para não consumir CPU
                usleep(10000); // 10ms
                continue;
            }
            
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            debugLog("Recebido: $line");
            
            $request = json_decode($line, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError(null, -32700, "Parse error: " . json_last_error_msg());
                continue;
            }
            
            $response = $this->handleRequest($request);
            
            if ($response !== null) {
                $this->sendResponse($response);
            }
        }
    }
    
    /**
     * Processa uma requisição JSON-RPC
     */
    private function handleRequest(array $request): ?array {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];
        
        debugLog("Método: $method, ID: " . json_encode($id));
        
        // Se não tem ID, é uma notificação (não precisa de resposta)
        if ($id === null && !in_array($method, ['initialize'])) {
            return null;
        }
        
        switch ($method) {
            case 'initialize':
                return $this->handleInitialize($id, $params);
                
            case 'initialized':
                // Notificação, não precisa de resposta
                return null;
                
            case 'tools/list':
                return $this->handleToolsList($id);
                
            case 'tools/call':
                return $this->handleToolCall($id, $params);
                
            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => []
                ];
                
            default:
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method not found: $method"
                    ]
                ];
        }
    }
    
    /**
     * Handle initialize request
     */
    private function handleInitialize(mixed $id, array $params): array {
        debugLog("Initialize request recebido");
        
        // Usar a versão do protocolo solicitada pelo cliente, ou padrão
        $clientProtocolVersion = $params['protocolVersion'] ?? '2024-11-05';
        
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $clientProtocolVersion,
                'capabilities' => [
                    'tools' => new \stdClass() // Objeto vazio para LM Studio
                ],
                'serverInfo' => [
                    'name' => self::SERVER_NAME,
                    'version' => self::SERVER_VERSION
                ]
            ]
        ];
    }
    
    /**
     * Handle tools/list request
     */
    private function handleToolsList(mixed $id): array {
        $tools = array_values($this->tools);
        
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => $tools
            ]
        ];
    }
    
    /**
     * Handle tools/call request
     */
    private function handleToolCall(mixed $id, array $params): array {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        
        debugLog("Tool call: $toolName com args: " . json_encode($arguments));
        
        if (!isset($this->tools[$toolName])) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32602,
                    'message' => "Tool not found: $toolName"
                ]
            ];
        }
        
        try {
            $result = match ($toolName) {
                'get_news' => $this->executeGetNews($arguments),
                'get_datetime' => $this->executeGetDatetime($arguments),
                'get_exchange_rate' => $this->executeGetExchangeRate($arguments),
                'get_weather' => $this->executeGetWeather($arguments),
                'search_wikipedia' => $this->executeSearchWikipedia($arguments),
                default => throw new Exception("Tool nao implementada: $toolName")
            };
            
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        ]
                    ]
                ]
            ];
        } catch (Exception $e) {
            debugLog("Erro na tool $toolName: " . $e->getMessage());
            
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Erro: " . $e->getMessage()
                        ]
                    ],
                    'isError' => true
                ]
            ];
        }
    }
    
    /**
     * Busca notícias do Google News via RSS
     */
    private function executeGetNews(array $args): array {
        $query = $args['query'] ?? '';
        $limit = min((int)($args['limit'] ?? 10), 50);
        $language = $args['language'] ?? 'pt-BR';
        
        // Construir URL do RSS do Google News
        $langCode = str_replace('-', '_', $language);
        $countryCode = explode('-', $language)[1] ?? 'BR';
        
        if (!empty($query)) {
            // Busca específica
            $url = "https://news.google.com/rss/search?q=" . urlencode($query) . "&hl={$langCode}&gl={$countryCode}&ceid={$countryCode}:" . explode('-', $language)[0];
        } else {
            // Top stories
            $url = "https://news.google.com/rss?hl={$langCode}&gl={$countryCode}&ceid={$countryCode}:" . explode('-', $language)[0];
        }
        
        debugLog("Buscando notícias de: $url");
        
        // Buscar RSS
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $rssContent = @file_get_contents($url, false, $context);
        
        if ($rssContent === false) {
            throw new Exception("Nao foi possivel acessar o Google News. Verifique sua conexao.");
        }
        
        // Parse RSS
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rssContent);
        
        if ($xml === false) {
            throw new Exception("Erro ao processar o feed RSS");
        }
        
        $news = [];
        $count = 0;
        
        foreach ($xml->channel->item as $item) {
            if ($count >= $limit) break;
            
            $pubDate = (string)$item->pubDate;
            $formattedDate = '';
            
            if (!empty($pubDate)) {
                try {
                    $date = new DateTime($pubDate);
                    $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                    $formattedDate = $date->format('d/m/Y H:i');
                } catch (Exception $e) {
                    $formattedDate = $pubDate;
                }
            }
            
            $news[] = [
                'titulo' => (string)$item->title,
                'link' => (string)$item->link,
                'fonte' => (string)$item->source,
                'data_publicacao' => $formattedDate,
                'descricao' => strip_tags((string)$item->description)
            ];
            
            $count++;
        }
        
        return [
            'total' => count($news),
            'query' => $query ?: 'Top Stories',
            'idioma' => $language,
            'noticias' => $news
        ];
    }
    
    /**
     * Retorna data e hora do sistema
     */
    private function executeGetDatetime(array $args): array {
        $timezone = $args['timezone'] ?? 'America/Sao_Paulo';
        $format = $args['format'] ?? 'completo';
        
        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $e) {
            throw new Exception("Timezone invalido: $timezone");
        }
        
        $now = new DateTime('now', $tz);
        
        // Nomes dos dias da semana em portugues
        $diasSemana = [
            'Sunday' => 'Domingo',
            'Monday' => 'Segunda-feira',
            'Tuesday' => 'Terca-feira',
            'Wednesday' => 'Quarta-feira',
            'Thursday' => 'Quinta-feira',
            'Friday' => 'Sexta-feira',
            'Saturday' => 'Sabado'
        ];
        
        // Nomes dos meses em portugues
        $meses = [
            'January' => 'Janeiro',
            'February' => 'Fevereiro',
            'March' => 'Marco',
            'April' => 'Abril',
            'May' => 'Maio',
            'June' => 'Junho',
            'July' => 'Julho',
            'August' => 'Agosto',
            'September' => 'Setembro',
            'October' => 'Outubro',
            'November' => 'Novembro',
            'December' => 'Dezembro'
        ];
        
        $diaSemana = $diasSemana[$now->format('l')] ?? $now->format('l');
        $mes = $meses[$now->format('F')] ?? $now->format('F');
        
        $result = [
            'timezone' => $timezone,
            'timestamp_unix' => $now->getTimestamp()
        ];
        
        switch ($format) {
            case 'data':
                $result['data'] = $now->format('d/m/Y');
                $result['dia_semana'] = $diaSemana;
                break;
                
            case 'hora':
                $result['hora'] = $now->format('H:i:s');
                break;
                
            case 'iso8601':
                $result['iso8601'] = $now->format('c');
                break;
                
            case 'completo':
            default:
                $result['data'] = $now->format('d/m/Y');
                $result['hora'] = $now->format('H:i:s');
                $result['dia_semana'] = $diaSemana;
                $result['mes'] = $mes;
                $result['ano'] = (int)$now->format('Y');
                $result['dia_do_ano'] = (int)$now->format('z') + 1;
                $result['semana_do_ano'] = (int)$now->format('W');
                $result['iso8601'] = $now->format('c');
                $result['formatado'] = "$diaSemana, " . $now->format('d') . " de $mes de " . $now->format('Y') . " - " . $now->format('H:i:s');
                break;
        }
        
        return $result;
    }
    
    /**
     * Obtém cotação de moedas (atual ou historica)
     * Suporta: latest (padrão), date (YYYY-MM-DD) ou start_date..end_date
     */
    private function executeGetExchangeRate(array $args): array {
        $from = strtoupper($args['from'] ?? 'USD');
        $to = strtoupper($args['to'] ?? 'BRL');
        $amount = (float)($args['amount'] ?? 1);

        $date = $args['date'] ?? null;
        $startDate = $args['start_date'] ?? null;
        $endDate = $args['end_date'] ?? null;

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "Accept: application/json\r\n"
            ]
        ]);

        // Histórico pontual (date)
        if (!empty($date)) {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                throw new Exception("Formato de data invalido. Use YYYY-MM-DD.");
            }

            $url = "https://api.frankfurter.app/{$date}?from={$from}&to={$to}&amount={$amount}";
            debugLog("Buscando cotação histórica (date): $url");

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                // Fallback para exchangerate.host
                $url2 = "https://api.exchangerate.host/{$date}?base={$from}&symbols={$to}&amount={$amount}";
                debugLog("Fallback cotação histórica: $url2");
                $response = @file_get_contents($url2, false, $context);

                if ($response === false) {
                    throw new Exception("Nao foi possivel obter a cotacao historica. Verifique sua conexao.");
                }

                $data = json_decode($response, true);
                if (!isset($data['rates'])) {
                    throw new Exception("Resposta invalida da API de cotacoes (fallback).");
                }

                $rates = [];
                foreach (explode(',', $to) as $currency) {
                    $currency = trim($currency);
                    if (isset($data['rates'][$currency])) {
                        $rate = $data['rates'][$currency];
                        $rates[$currency] = [
                            'taxa' => $rate,
                            'valor_convertido' => round($rate * $amount, 4)
                        ];
                    }
                }

                return [
                    'moeda_origem' => $from,
                    'valor_origem' => $amount,
                    'data' => $data['date'] ?? $date,
                    'cotacoes' => $rates
                ];
            }

            $data = json_decode($response, true);
            if (!isset($data['rates'])) {
                throw new Exception("Resposta invalida da API Frankfurter");
            }

            $rates = [];
            foreach ($data['rates'] as $currency => $rate) {
                $rates[$currency] = [
                    'taxa' => $rate / $amount,
                    'valor_convertido' => $rate
                ];
            }

            return [
                'moeda_origem' => $from,
                'valor_origem' => $amount,
                'data' => $data['date'] ?? $date,
                'cotacoes' => $rates
            ];
        }

        // Histórico por intervalo (start_date .. end_date)
        if (!empty($startDate) && !empty($endDate)) {
            $start = DateTime::createFromFormat('Y-m-d', $startDate);
            $end = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
                throw new Exception("Formato de data invalido. Use YYYY-MM-DD.");
            }

            // Frankfurter suporta /start..end
            $url = "https://api.frankfurter.app/{$startDate}..{$endDate}?from={$from}&to={$to}";
            debugLog("Buscando cotação histórica (range): $url");

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                // Fallback para exchangerate.host timeseries
                $url2 = "https://api.exchangerate.host/timeseries?start_date={$startDate}&end_date={$endDate}&base={$from}&symbols={$to}";
                debugLog("Fallback timeseries: $url2");
                $response = @file_get_contents($url2, false, $context);

                if ($response === false) {
                    throw new Exception("Nao foi possivel obter as cotacoes historicas. Verifique sua conexao.");
                }

                $data = json_decode($response, true);
                if (!isset($data['rates'])) {
                    throw new Exception("Resposta invalida da API de cotacoes (timeseries).");
                }

                $ratesByDate = [];
                foreach ($data['rates'] as $dt => $rates) {
                    $row = [];
                    foreach (explode(',', $to) as $currency) {
                        $currency = trim($currency);
                        if (isset($rates[$currency])) {
                            $rate = $rates[$currency];
                            $row[$currency] = [
                                'taxa' => $rate,
                                'valor_convertido' => round($rate * $amount, 4)
                            ];
                        }
                    }
                    $ratesByDate[$dt] = $row;
                }

                return [
                    'moeda_origem' => $from,
                    'valor_origem' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'cotacoes' => $ratesByDate
                ];
            }

            $data = json_decode($response, true);
            if (!isset($data['rates'])) {
                throw new Exception("Resposta invalida da API Frankfurter");
            }

            $ratesByDate = [];
            foreach ($data['rates'] as $dt => $row) {
                $ratesRow = [];
                foreach ($row as $currency => $value) {
                    $ratesRow[$currency] = [
                        'taxa' => $value,
                        'valor_convertido' => round($value * $amount, 4)
                    ];
                }
                $ratesByDate[$dt] = $ratesRow;
            }

            return [
                'moeda_origem' => $from,
                'valor_origem' => $amount,
                'start_date' => $data['start_at'] ?? $startDate,
                'end_date' => $data['end_at'] ?? $endDate,
                'cotacoes' => $ratesByDate
            ];
        }

        // Padrão: latest (mantém comportamento anterior)
        $url = "https://api.frankfurter.app/latest?from={$from}&to={$to}&amount={$amount}";
        debugLog("Buscando cotação de: $url");

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            // Tentar API alternativa: Exchange Rate API (tambem gratuita)
            $url2 = "https://open.er-api.com/v6/latest/{$from}";
            $response = @file_get_contents($url2, false, $context);

            if ($response === false) {
                throw new Exception("Nao foi possivel obter as cotacoes. Verifique sua conexao.");
            }

            $data = json_decode($response, true);

            if (!isset($data['rates'])) {
                throw new Exception("Resposta invalida da API de cotacoes");
            }

            $currencies = explode(',', $to);
            $rates = [];

            foreach ($currencies as $currency) {
                $currency = trim($currency);
                if (isset($data['rates'][$currency])) {
                    $rate = $data['rates'][$currency];
                    $rates[$currency] = [
                        'taxa' => $rate,
                        'valor_convertido' => round($rate * $amount, 4)
                    ];
                }
            }

            return [
                'moeda_origem' => $from,
                'valor_origem' => $amount,
                'data' => $data['time_last_update_utc'] ?? date('Y-m-d'),
                'cotacoes' => $rates
            ];
        }

        $data = json_decode($response, true);

        if (!isset($data['rates'])) {
            throw new Exception("Resposta invalida da API Frankfurter");
        }

        $rates = [];
        foreach ($data['rates'] as $currency => $rate) {
            $rates[$currency] = [
                'taxa' => $rate / $amount,
                'valor_convertido' => $rate
            ];
        }

        return [
            'moeda_origem' => $from,
            'valor_origem' => $amount,
            'data' => $data['date'],
            'cotacoes' => $rates
        ];
    }
    
    /**
     * Obtem previsao do tempo via Open-Meteo API
     */
    private function executeGetWeather(array $args): array {
        $city = $args['city'] ?? '';
        $latitude = $args['latitude'] ?? null;
        $longitude = $args['longitude'] ?? null;
        $mode = $args['mode'] ?? 'current';
        $startDate = $args['start_date'] ?? '';
        $endDate = $args['end_date'] ?? '';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "Accept: application/json\r\n"
            ]
        ]);
        
        // Se cidade foi informada, buscar coordenadas via Geocoding API
        if (!empty($city) && ($latitude === null || $longitude === null)) {
            $geoUrl = "https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($city) . "&count=1&language=pt&format=json";
            debugLog("Buscando coordenadas: $geoUrl");
            
            $geoResponse = @file_get_contents($geoUrl, false, $context);
            
            if ($geoResponse === false) {
                throw new Exception("Nao foi possivel buscar a cidade. Verifique sua conexao.");
            }
            
            $geoData = json_decode($geoResponse, true);
            
            if (!isset($geoData['results'][0])) {
                throw new Exception("Cidade nao encontrada: $city");
            }
            
            $location = $geoData['results'][0];
            $latitude = $location['latitude'];
            $longitude = $location['longitude'];
            $cityName = $location['name'];
            $country = $location['country'] ?? '';
            $admin = $location['admin1'] ?? '';
        } else {
            $cityName = $city ?: "Lat: $latitude, Lon: $longitude";
            $country = '';
            $admin = '';
        }
        
        if ($latitude === null || $longitude === null) {
            throw new Exception("Informe a cidade ou as coordenadas (latitude/longitude).");
        }
        
        // Descricoes das condicoes do tempo
        $weatherCodes = [
            0 => 'Ceu limpo',
            1 => 'Principalmente limpo',
            2 => 'Parcialmente nublado',
            3 => 'Nublado',
            45 => 'Neblina',
            48 => 'Neblina com geada',
            51 => 'Garoa leve',
            53 => 'Garoa moderada',
            55 => 'Garoa intensa',
            56 => 'Garoa congelante leve',
            57 => 'Garoa congelante intensa',
            61 => 'Chuva leve',
            63 => 'Chuva moderada',
            65 => 'Chuva forte',
            66 => 'Chuva congelante leve',
            67 => 'Chuva congelante forte',
            71 => 'Neve leve',
            73 => 'Neve moderada',
            75 => 'Neve forte',
            77 => 'Graos de neve',
            80 => 'Pancadas de chuva leves',
            81 => 'Pancadas de chuva moderadas',
            82 => 'Pancadas de chuva violentas',
            85 => 'Pancadas de neve leves',
            86 => 'Pancadas de neve fortes',
            95 => 'Trovoada',
            96 => 'Trovoada com granizo leve',
            99 => 'Trovoada com granizo forte'
        ];
        
        $result = [
            'localizacao' => [
                'cidade' => $cityName,
                'estado' => $admin,
                'pais' => $country,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]
        ];
        
        switch ($mode) {
            case 'current':
                // Clima atual
                $url = "https://api.open-meteo.com/v1/forecast?latitude=$latitude&longitude=$longitude&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m,wind_direction_10m&timezone=America/Sao_Paulo";
                debugLog("Buscando clima atual: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    throw new Exception("Nao foi possivel obter o clima. Verifique sua conexao.");
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['current'])) {
                    throw new Exception("Resposta invalida da API de clima.");
                }
                
                $current = $data['current'];
                $weatherCode = $current['weather_code'] ?? 0;
                
                $result['modo'] = 'atual';
                $result['clima_atual'] = [
                    'temperatura' => $current['temperature_2m'] . ' C',
                    'sensacao_termica' => $current['apparent_temperature'] . ' C',
                    'umidade' => $current['relative_humidity_2m'] . '%',
                    'precipitacao' => $current['precipitation'] . ' mm',
                    'vento_velocidade' => $current['wind_speed_10m'] . ' km/h',
                    'vento_direcao' => $current['wind_direction_10m'] . ' graus',
                    'condicao' => $weatherCodes[$weatherCode] ?? 'Desconhecido',
                    'codigo_condicao' => $weatherCode,
                    'horario' => $current['time']
                ];
                break;
                
            case 'forecast':
                // Previsao para os proximos 7 dias
                $url = "https://api.open-meteo.com/v1/forecast?latitude=$latitude&longitude=$longitude&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max&timezone=America/Sao_Paulo";
                debugLog("Buscando previsao: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    throw new Exception("Nao foi possivel obter a previsao. Verifique sua conexao.");
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['daily'])) {
                    throw new Exception("Resposta invalida da API de previsao.");
                }
                
                $daily = $data['daily'];
                $previsao = [];
                
                for ($i = 0; $i < count($daily['time']); $i++) {
                    $weatherCode = $daily['weather_code'][$i] ?? 0;
                    $previsao[] = [
                        'data' => $daily['time'][$i],
                        'temp_maxima' => $daily['temperature_2m_max'][$i] . ' C',
                        'temp_minima' => $daily['temperature_2m_min'][$i] . ' C',
                        'precipitacao' => $daily['precipitation_sum'][$i] . ' mm',
                        'prob_chuva' => $daily['precipitation_probability_max'][$i] . '%',
                        'vento_max' => $daily['wind_speed_10m_max'][$i] . ' km/h',
                        'condicao' => $weatherCodes[$weatherCode] ?? 'Desconhecido'
                    ];
                }
                
                $result['modo'] = 'previsao_7_dias';
                $result['previsao'] = $previsao;
                break;
                
            case 'history':
                // Historico de clima
                if (empty($startDate) || empty($endDate)) {
                    throw new Exception("Para historico, informe start_date e end_date (formato: YYYY-MM-DD).");
                }
                
                // Validar datas
                $start = DateTime::createFromFormat('Y-m-d', $startDate);
                $end = DateTime::createFromFormat('Y-m-d', $endDate);
                
                if (!$start || !$end) {
                    throw new Exception("Formato de data invalido. Use YYYY-MM-DD.");
                }
                
                // API de historico do Open-Meteo
                $url = "https://archive-api.open-meteo.com/v1/archive?latitude=$latitude&longitude=$longitude&start_date=$startDate&end_date=$endDate&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max&timezone=America/Sao_Paulo";
                debugLog("Buscando historico: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    throw new Exception("Nao foi possivel obter o historico. Verifique sua conexao.");
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['daily'])) {
                    throw new Exception("Resposta invalida da API de historico. Verifique se as datas sao validas (ate ontem).");
                }
                
                $daily = $data['daily'];
                $historico = [];
                
                for ($i = 0; $i < count($daily['time']); $i++) {
                    $weatherCode = $daily['weather_code'][$i] ?? 0;
                    $historico[] = [
                        'data' => $daily['time'][$i],
                        'temp_maxima' => ($daily['temperature_2m_max'][$i] ?? 'N/A') . ' C',
                        'temp_minima' => ($daily['temperature_2m_min'][$i] ?? 'N/A') . ' C',
                        'precipitacao' => ($daily['precipitation_sum'][$i] ?? 0) . ' mm',
                        'vento_max' => ($daily['wind_speed_10m_max'][$i] ?? 'N/A') . ' km/h',
                        'condicao' => $weatherCodes[$weatherCode] ?? 'Desconhecido'
                    ];
                }
                
                $result['modo'] = 'historico';
                $result['periodo'] = [
                    'inicio' => $startDate,
                    'fim' => $endDate
                ];
                $result['historico'] = $historico;
                break;
                
            default:
                throw new Exception("Modo invalido: $mode. Use 'current', 'forecast' ou 'history'.");
        }
        
        return $result;
    }
    
    /**
     * Busca artigos na Wikipedia via API gratuita
     */
    private function executeSearchWikipedia(array $args): array {
        $query = $args['query'] ?? '';
        $language = $args['language'] ?? 'pt';
        $mode = $args['mode'] ?? 'summary';
        $limit = min((int)($args['limit'] ?? 5), 20);
        
        if (empty($query)) {
            throw new Exception("O parametro 'query' e obrigatorio.");
        }
        
        // Sanitizar o codigo de idioma (apenas letras)
        $language = preg_replace('/[^a-z]/', '', strtolower($language));
        if (empty($language)) {
            $language = 'pt';
        }
        
        $baseUrl = "https://{$language}.wikipedia.org/w/api.php";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: MCPServer/1.0 (MCP PHP Server)\r\n"
            ]
        ]);
        
        switch ($mode) {
            case 'search':
                // Buscar artigos por termo
                $params = http_build_query([
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => $query,
                    'srlimit' => $limit,
                    'srinfo' => 'totalhits',
                    'srprop' => 'snippet|titlesnippet|wordcount|timestamp',
                    'format' => 'json',
                    'utf8' => 1
                ]);
                
                $url = "$baseUrl?$params";
                debugLog("Wikipedia search: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    throw new Exception("Nao foi possivel acessar a Wikipedia. Verifique sua conexao.");
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['query']['search'])) {
                    throw new Exception("Resposta invalida da Wikipedia.");
                }
                
                $results = [];
                foreach ($data['query']['search'] as $item) {
                    $results[] = [
                        'titulo' => $item['title'],
                        'resumo' => strip_tags($item['snippet']),
                        'palavras' => $item['wordcount'],
                        'ultima_edicao' => $item['timestamp'],
                        'link' => "https://{$language}.wikipedia.org/wiki/" . urlencode(str_replace(' ', '_', $item['title']))
                    ];
                }
                
                return [
                    'query' => $query,
                    'idioma' => $language,
                    'total_encontrados' => $data['query']['searchinfo']['totalhits'] ?? count($results),
                    'resultados' => $results
                ];
                
            case 'summary':
                // Resumo do artigo via REST API
                $encodedQuery = urlencode($query);
                $url = "https://{$language}.wikipedia.org/api/rest_v1/page/summary/{$encodedQuery}";
                debugLog("Wikipedia summary: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    // Tentar buscar o titulo correto primeiro
                    return $this->executeSearchWikipedia([
                        'query' => $query,
                        'language' => $language,
                        'mode' => 'search',
                        'limit' => 3
                    ]);
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['title'])) {
                    throw new Exception("Artigo nao encontrado: $query");
                }
                
                $result = [
                    'titulo' => $data['title'],
                    'descricao' => $data['description'] ?? '',
                    'resumo' => $data['extract'] ?? '',
                    'idioma' => $language,
                    'link' => $data['content_urls']['desktop']['page'] ?? '',
                    'ultima_edicao' => $data['timestamp'] ?? ''
                ];
                
                if (isset($data['thumbnail'])) {
                    $result['imagem'] = $data['thumbnail']['source'];
                }
                
                return $result;
                
            case 'full':
                // Artigo completo (texto extraido)
                $params = http_build_query([
                    'action' => 'query',
                    'titles' => $query,
                    'prop' => 'extracts|info|categories',
                    'exintro' => false,
                    'explaintext' => true,
                    'exsectionformat' => 'plain',
                    'inprop' => 'url',
                    'cllimit' => 10,
                    'format' => 'json',
                    'utf8' => 1
                ]);
                
                $url = "$baseUrl?$params";
                debugLog("Wikipedia full: $url");
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    throw new Exception("Nao foi possivel acessar a Wikipedia. Verifique sua conexao.");
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['query']['pages'])) {
                    throw new Exception("Resposta invalida da Wikipedia.");
                }
                
                $page = reset($data['query']['pages']);
                
                if (isset($page['missing'])) {
                    // Artigo nao encontrado, tentar busca
                    return $this->executeSearchWikipedia([
                        'query' => $query,
                        'language' => $language,
                        'mode' => 'search',
                        'limit' => 3
                    ]);
                }
                
                $categories = [];
                if (isset($page['categories'])) {
                    foreach ($page['categories'] as $cat) {
                        $categories[] = str_replace('Categoria:', '', $cat['title']);
                    }
                }
                
                // Limitar texto a ~4000 caracteres para nao sobrecarregar
                $extract = $page['extract'] ?? '';
                $truncated = false;
                if (mb_strlen($extract) > 4000) {
                    $extract = mb_substr($extract, 0, 4000) . '...';
                    $truncated = true;
                }
                
                return [
                    'titulo' => $page['title'],
                    'idioma' => $language,
                    'conteudo' => $extract,
                    'truncado' => $truncated,
                    'categorias' => $categories,
                    'link' => $page['fullurl'] ?? "https://{$language}.wikipedia.org/wiki/" . urlencode(str_replace(' ', '_', $page['title']))
                ];
                
            default:
                throw new Exception("Modo invalido: $mode. Use 'search', 'summary' ou 'full'.");
        }
    }
    
    /**
     * Envia resposta para stdout
     */
    private function sendResponse(array $response): void {
        $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        debugLog("Enviando: $json");
        echo $json . "\n";
        flush();
    }
    
    /**
     * Envia erro para stdout
     */
    private function sendError(mixed $id, int $code, string $message): void {
        $this->sendResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ]);
    }
}

// Iniciar o servidor
$server = new McpServer();
$server->run();
