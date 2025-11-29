<?php
/**
 * McpBridgeHandler - Gestionnaire de bridge MCP vers modules RPC
 *
 * Route les appels MCP tools vers les modules Cortex via leurs sockets RPC.
 * Les configurations sont chargées depuis Redis (clés mcp_<module>).
 *
 * Architecture:
 *   MCP Request (tools/call) → McpBridgeHandler → RPC Socket → Module
 *
 * Usage dans mod_mcp.php:
 *   $bridge = McpBridgeHandler::getInstance();
 *   if ($bridge->isBridgedTool($toolName)) {
 *       $result = $bridge->callTool($toolName, $arguments);
 *   }
 *
 * @author Olivier Ibanez & Claude
 * @version 1.0
 * @date 2025-11-28
 */

class McpBridgeHandler
{
    /** @var McpBridgeHandler Singleton instance */
    private static ?McpBridgeHandler $instance = null;

    /** @var array Cache des configs modules */
    private array $moduleConfigs = [];

    /** @var array Index tool_name → module_name pour lookup rapide */
    private array $toolIndex = [];

    /** @var bool Configs chargées depuis Redis */
    private bool $loaded = false;

    /** @var string Préfixe Redis */
    private string $redisPrefix = 'mcp_';

    /** @var int Timeout RPC en secondes */
    private int $rpcTimeout = 30;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
    }

    /**
     * Obtenir l'instance singleton
     *
     * @return McpBridgeHandler
     */
    public static function getInstance(): McpBridgeHandler
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charge les configurations des modules bridgés depuis Redis
     *
     * @param bool $force Force le rechargement même si déjà chargé
     * @return int Nombre de modules chargés
     */
    public function loadConfigs(bool $force = false): int
    {
        if ($this->loaded && !$force) {
            return count($this->moduleConfigs);
        }

        $this->moduleConfigs = [];
        $this->toolIndex = [];

        try {
            // Récupérer toutes les clés mcp_*
            $keys = \MyRedis::getInstance("mcpBridge")->keys($this->redisPrefix . '*');

            foreach ($keys as $key) {
                $json = \MyRedis::getInstance("mcpBridge")->get($key);
                if (!$json) continue;

                $config = json_decode($json, true);
                if (!$config || !isset($config['module'])) continue;

                $moduleName = $config['module'];
                $this->moduleConfigs[$moduleName] = $config;

                // Indexer les tools pour lookup rapide
                if (!empty($config['tools'])) {
                    foreach ($config['tools'] as $tool) {
                        $this->toolIndex[$tool['name']] = $moduleName;
                    }
                }

                error_log("[McpBridgeHandler] Loaded module: $moduleName with " .
                          count($config['tools'] ?? []) . " tools");
            }

            $this->loaded = true;

        } catch (\Throwable $e) {
            error_log("[McpBridgeHandler] Failed to load configs: " . $e->getMessage());
        }

        return count($this->moduleConfigs);
    }

    /**
     * Vérifie si un tool est géré par le bridge
     *
     * @param string $toolName Nom du tool MCP
     * @return bool
     */
    public function isBridgedTool(string $toolName): bool
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        return isset($this->toolIndex[$toolName]);
    }

    /**
     * Obtient le module responsable d'un tool
     *
     * @param string $toolName Nom du tool
     * @return string|null Nom du module ou null
     */
    public function getModuleForTool(string $toolName): ?string
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        return $this->toolIndex[$toolName] ?? null;
    }

    /**
     * Obtient la configuration d'un module
     *
     * @param string $moduleName Nom du module
     * @return array|null Configuration ou null
     */
    public function getModuleConfig(string $moduleName): ?array
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }
        return $this->moduleConfigs[$moduleName] ?? null;
    }

    /**
     * Appelle un tool bridgé via RPC
     *
     * @param string $toolName Nom du tool MCP
     * @param array $arguments Arguments du tool
     * @return array Résultat de l'appel
     * @throws \RuntimeException Si le tool n'est pas trouvé ou l'appel échoue
     */
    public function callTool(string $toolName, array $arguments): array
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }

        // Trouver le module
        $moduleName = $this->toolIndex[$toolName] ?? null;
        if ($moduleName === null) {
            throw new \RuntimeException("Tool not found in bridge: $toolName");
        }

        $config = $this->moduleConfigs[$moduleName];
        $socketPath = $config['socket'] ?? null;

        if (!$socketPath) {
            throw new \RuntimeException("No socket configured for module: $moduleName");
        }

        // Trouver l'action RPC correspondante
        $action = $config['tool_actions'][$toolName] ?? $toolName;

        // Construire la requête RPC
        $request = [
            'action' => $action,
            ...$arguments
        ];

        // Appeler via RPC socket
        return $this->rpcCall($socketPath, $request);
    }

    /**
     * Effectue un appel RPC vers un socket Unix
     *
     * @param string $socketPath Chemin du socket
     * @param array $request Requête à envoyer
     * @return array Réponse
     * @throws \RuntimeException En cas d'erreur
     */
    private function rpcCall(string $socketPath, array $request): array
    {
        // Vérifier que le socket existe
        if (!file_exists($socketPath)) {
            throw new \RuntimeException("RPC socket not found: $socketPath");
        }

        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket === false) {
            throw new \RuntimeException("Failed to create socket: " . socket_strerror(socket_last_error()));
        }

        // Timeout
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => $this->rpcTimeout,
            'usec' => 0
        ]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec' => $this->rpcTimeout,
            'usec' => 0
        ]);

        try {
            // Connexion
            if (@socket_connect($socket, $socketPath) === false) {
                throw new \RuntimeException("Failed to connect to socket: " .
                    socket_strerror(socket_last_error($socket)));
            }

            // Envoyer la requête
            $json = json_encode($request);
            $sent = @socket_send($socket, $json . "\n", strlen($json) + 1, 0);
            if ($sent === false) {
                throw new \RuntimeException("Failed to send request: " .
                    socket_strerror(socket_last_error($socket)));
            }

            // Lire la réponse
            $response = '';
            $buffer = '';
            while (($bytes = @socket_recv($socket, $buffer, 8192, 0)) > 0) {
                $response .= $buffer;
                // Vérifier si on a une réponse JSON complète
                if (strpos($response, "\n") !== false) {
                    break;
                }
            }

            if (empty($response)) {
                throw new \RuntimeException("Empty response from RPC server");
            }

            $result = json_decode(trim($response), true);
            if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON response: " . json_last_error_msg());
            }

            return $result;

        } finally {
            socket_close($socket);
        }
    }

    /**
     * Obtient la liste de tous les tools bridgés
     *
     * @return array Liste des tools avec leurs infos
     */
    public function getAllBridgedTools(): array
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }

        $tools = [];
        foreach ($this->moduleConfigs as $config) {
            foreach ($config['tools'] ?? [] as $tool) {
                $tools[] = [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'module' => $config['module'],
                    'inputSchema' => $tool['inputSchema']
                ];
            }
        }

        return $tools;
    }

    /**
     * Obtient les définitions de tools au format MCP
     *
     * @return array Tools au format MCP pour tools/list
     */
    public function getToolsForMcp(): array
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }

        $mcpTools = [];
        foreach ($this->moduleConfigs as $config) {
            foreach ($config['tools'] ?? [] as $tool) {
                $mcpTools[] = [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'inputSchema' => $tool['inputSchema']
                ];
            }
        }

        return $mcpTools;
    }

    /**
     * Recharge les configurations (utile après ajout d'un nouveau module)
     *
     * @return int Nombre de modules chargés
     */
    public function reload(): int
    {
        return $this->loadConfigs(true);
    }

    /**
     * Obtient les statistiques du bridge
     *
     * @return array Statistiques
     */
    public function getStats(): array
    {
        if (!$this->loaded) {
            $this->loadConfigs();
        }

        $totalTools = 0;
        $moduleStats = [];

        foreach ($this->moduleConfigs as $moduleName => $config) {
            $toolCount = count($config['tools'] ?? []);
            $totalTools += $toolCount;
            $moduleStats[$moduleName] = [
                'tools' => $toolCount,
                'socket' => $config['socket'] ?? 'N/A',
                'generated' => $config['generated_at'] ?? 'N/A'
            ];
        }

        return [
            'modules_count' => count($this->moduleConfigs),
            'tools_count' => $totalTools,
            'modules' => $moduleStats
        ];
    }

    /**
     * Définit le timeout RPC
     *
     * @param int $seconds Timeout en secondes
     */
    public function setRpcTimeout(int $seconds): void
    {
        $this->rpcTimeout = max(1, $seconds);
    }
}
