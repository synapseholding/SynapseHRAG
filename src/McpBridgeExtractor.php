<?php
/**
 * McpBridgeExtractor - Extracteur de configuration MCP depuis les attributs PHP 8
 *
 * Analyse les fichiers source PHP pour extraire les fonctions annotées avec
 * #[McpTool] et #[Schema], génère la configuration JSON pour le bridge MCP.
 *
 * Usage:
 *   $extractor = new McpBridgeExtractor();
 *   $config = $extractor->extractFromFile('/path/to/mod_xxx.php', 'modulename');
 *   $extractor->storeInRedis($config);
 *
 * @author Olivier Ibanez & Claude
 * @version 1.0
 * @date 2025-11-28
 */

class McpBridgeExtractor
{
    /** @var string Préfixe Redis pour les configs MCP */
    private string $redisPrefix = 'mcp_';

    /** @var string Pattern de socket par défaut */
    private string $socketPattern = '/var/run/cortex_%s.sock';

    /**
     * Extrait la configuration MCP depuis un fichier source PHP
     *
     * @param string $filePath Chemin vers le fichier source
     * @param string|null $moduleName Nom du module (déduit du fichier si null)
     * @param string|null $className Nom de la classe (déduit du fichier si null)
     * @return array|null Configuration MCP ou null si pas de tools trouvés
     */
    public function extractFromFile(string $filePath, ?string $moduleName = null, ?string $className = null): ?array
    {
        if (!file_exists($filePath)) {
            error_log("[McpBridgeExtractor] File not found: $filePath");
            return null;
        }

        $code = file_get_contents($filePath);

        // Déduire le nom du module depuis le fichier
        if ($moduleName === null) {
            $basename = basename($filePath, '.php');
            if (str_starts_with($basename, 'mod_')) {
                $moduleName = substr($basename, 4);
            } else {
                $moduleName = $basename;
            }
        }

        // Déduire le nom de la classe
        if ($className === null) {
            if (preg_match('/class\s+(\w+)/', $code, $m)) {
                $className = $m[1];
            } else {
                $className = ucfirst($moduleName);
            }
        }

        // Extraire les tools MCP
        $tools = $this->extractTools($code);

        if (empty($tools)) {
            return null;
        }

        // Construire le mapping tool → action RPC
        $toolActions = [];
        foreach ($tools as &$tool) {
            $tool['class'] = $className;

            // Mapping: hrag_search → search
            $action = $tool['name'];
            if (str_starts_with($action, $moduleName . '_')) {
                $action = substr($action, strlen($moduleName) + 1);
            }
            $toolActions[$tool['name']] = $action;
        }

        return [
            'module' => $moduleName,
            'class' => $className,
            'socket' => sprintf($this->socketPattern, $moduleName),
            'tools' => $tools,
            'tool_actions' => $toolActions,
            'tools_count' => count($tools),
            'source_file' => $filePath,
            'generated_at' => date('c')
        ];
    }

    /**
     * Extrait les tools MCP depuis le code source
     *
     * @param string $code Code source PHP
     * @return array Liste des tools extraits
     */
    private function extractTools(string $code): array
    {
        $tools = [];

        // Pattern pour capturer #[McpTool(...)] et la signature de fonction
        $pattern = '/#\[McpTool\(([^)]+)\)\]\s*(public\s+static\s+function\s+(\w+)\s*\(([\s\S]*?)\)\s*:\s*\w+)/';

        if (!preg_match_all($pattern, $code, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $mcpToolArgs = $match[1];
            $methodName = $match[3];
            $paramsBlock = $match[4];

            // Parser les arguments de McpTool
            $toolName = $methodName;
            $description = '';

            if (preg_match("/name:\s*['\"]([^'\"]+)['\"]/", $mcpToolArgs, $m)) {
                $toolName = $m[1];
            }
            if (preg_match("/description:\s*['\"]([^'\"]+)['\"]/", $mcpToolArgs, $m)) {
                $description = $m[1];
            }

            // Parser les paramètres
            $params = $this->parseParameters($paramsBlock);
            $required = [];

            foreach ($params as $paramName => $paramSchema) {
                $hasDefault = isset($paramSchema['default']) ||
                              (isset($paramSchema['nullable']) && $paramSchema['nullable']);
                if (!$hasDefault) {
                    $required[] = $paramName;
                }
                // Nettoyer les clés internes
                unset($params[$paramName]['nullable']);
            }

            $tools[] = [
                'name' => $toolName,
                'description' => $description,
                'method' => $methodName,
                'class' => '', // Sera rempli par extractFromFile
                'static' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $params,
                    'required' => $required
                ]
            ];
        }

        return $tools;
    }

    /**
     * Parse les paramètres avec leurs attributs #[Schema]
     *
     * @param string $paramsBlock Bloc de paramètres de la fonction
     * @return array Paramètres avec leurs schemas
     */
    private function parseParameters(string $paramsBlock): array
    {
        $params = [];

        // Normaliser les espaces
        $paramsBlock = preg_replace('/\s+/', ' ', $paramsBlock);

        // Pattern pour chaque paramètre avec son #[Schema]
        $pattern = '/#\[Schema\(([^]]+)\]\s*\)?\s*(\?)?([\w\\\\]+)\s+\$(\w+)(?:\s*=\s*(\[[^\]]*\]|null|true|false|[\d.]+|[\'"][^\'"]*[\'"]))?/';

        if (!preg_match_all($pattern, $paramsBlock, $paramMatches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($paramMatches as $pm) {
            $schemaAttrs = $pm[1];
            $isNullable = !empty($pm[2]);
            $paramName = $pm[4];
            $defaultValue = isset($pm[5]) ? trim($pm[5]) : null;

            $schema = $this->parseSchemaAttributes($schemaAttrs);
            $schema['nullable'] = $isNullable;

            if ($defaultValue !== null) {
                $schema['default'] = $this->parseDefaultValue($defaultValue);
            }

            $params[$paramName] = $schema;
        }

        return $params;
    }

    /**
     * Parse les attributs d'un #[Schema]
     *
     * @param string $attrs Contenu des attributs Schema
     * @return array Schema JSON
     */
    private function parseSchemaAttributes(string $attrs): array
    {
        $schema = [];

        // Type
        if (preg_match("/type:\s*['\"](\w+)['\"]/", $attrs, $m)) {
            $schema['type'] = $m[1];
        }

        // Description
        if (preg_match("/description:\s*['\"]([^'\"]+)['\"]/", $attrs, $m)) {
            $schema['description'] = $m[1];
        }

        // Contraintes numériques
        if (preg_match('/minimum:\s*([\d.]+)/', $attrs, $m)) {
            $schema['minimum'] = floatval($m[1]);
        }
        if (preg_match('/maximum:\s*([\d.]+)/', $attrs, $m)) {
            $schema['maximum'] = floatval($m[1]);
        }

        // Contraintes string
        if (preg_match('/minLength:\s*(\d+)/', $attrs, $m)) {
            $schema['minLength'] = intval($m[1]);
        }
        if (preg_match('/maxLength:\s*(\d+)/', $attrs, $m)) {
            $schema['maxLength'] = intval($m[1]);
        }

        // Pattern
        if (preg_match("/pattern:\s*['\"]([^'\"]+)['\"]/", $attrs, $m)) {
            $schema['pattern'] = $m[1];
        }

        // Enum
        if (preg_match('/enum:\s*\[([^\]]+)\]/', $attrs, $m)) {
            preg_match_all("/['\"]([^'\"]+)['\"]/", $m[1], $enumMatches);
            if (!empty($enumMatches[1])) {
                $schema['enum'] = $enumMatches[1];
            }
        }

        // Format
        if (preg_match("/format:\s*['\"](\w+)['\"]/", $attrs, $m)) {
            $schema['format'] = $m[1];
        }

        return $schema;
    }

    /**
     * Parse une valeur par défaut
     *
     * @param string $value Valeur brute
     * @return mixed Valeur parsée
     */
    private function parseDefaultValue(string $value)
    {
        $value = trim($value);

        if ($value === 'null') return null;
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        // Array vide
        if ($value === '[]') return [];

        // Array avec valeurs
        if (preg_match('/^\[(.+)\]$/', $value, $m)) {
            preg_match_all("/['\"]([^'\"]+)['\"]/", $m[1], $arrMatches);
            if (!empty($arrMatches[1])) {
                return $arrMatches[1];
            }
            return [];
        }

        // Nombre
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? floatval($value) : intval($value);
        }

        // String
        return trim($value, "'\"");
    }

    /**
     * Stocke la configuration dans Redis
     *
     * @param array $config Configuration MCP
     * @param \Redis|null $redis Instance Redis (utilise MyRedis si null)
     * @return bool Succès
     */
    public function storeInRedis(array $config, $redis = null): bool
    {
        $key = $this->redisPrefix . $config['module'];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            if ($redis === null) {
                // Utilise MyRedis du framework Cortex
                if (class_exists('MyRedis')) {
                    \MyRedis::getInstance("mcpBridge")->set($key, $json);
                    return true;
                }
                error_log("[McpBridgeExtractor] MyRedis not available");
                return false;
            }

            $redis->set($key, $json);
            return true;
        } catch (\Throwable $e) {
            error_log("[McpBridgeExtractor] Redis error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Charge une configuration depuis Redis
     *
     * @param string $moduleName Nom du module
     * @param \Redis|null $redis Instance Redis
     * @return array|null Configuration ou null
     */
    public function loadFromRedis(string $moduleName, $redis = null): ?array
    {
        $key = $this->redisPrefix . $moduleName;

        try {
            if ($redis === null) {
                if (class_exists('MyRedis')) {
                    $json = \MyRedis::getInstance("mcpBridge")->get($key);
                    return $json ? json_decode($json, true) : null;
                }
                return null;
            }

            $json = $redis->get($key);
            return $json ? json_decode($json, true) : null;
        } catch (\Throwable $e) {
            error_log("[McpBridgeExtractor] Redis read error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liste tous les modules MCP enregistrés dans Redis
     *
     * @param \Redis|null $redis Instance Redis
     * @return array Liste des noms de modules
     */
    public function listRegisteredModules($redis = null): array
    {
        $pattern = $this->redisPrefix . '*';
        $modules = [];

        try {
            if ($redis === null) {
                if (class_exists('MyRedis')) {
                    $keys = \MyRedis::getInstance("mcpBridge")->keys($pattern);
                    foreach ($keys as $key) {
                        $modules[] = substr($key, strlen($this->redisPrefix));
                    }
                    return $modules;
                }
                return [];
            }

            $keys = $redis->keys($pattern);
            foreach ($keys as $key) {
                $modules[] = substr($key, strlen($this->redisPrefix));
            }
            return $modules;
        } catch (\Throwable $e) {
            error_log("[McpBridgeExtractor] Redis keys error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Génère le JSON formaté pour debug/affichage
     *
     * @param array $config Configuration MCP
     * @return string JSON formaté
     */
    public function toJson(array $config): string
    {
        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Définit le pattern de socket personnalisé
     *
     * @param string $pattern Pattern avec %s pour le nom du module
     */
    public function setSocketPattern(string $pattern): void
    {
        $this->socketPattern = $pattern;
    }

    /**
     * Définit le préfixe Redis
     *
     * @param string $prefix Préfixe Redis
     */
    public function setRedisPrefix(string $prefix): void
    {
        $this->redisPrefix = $prefix;
    }
}
