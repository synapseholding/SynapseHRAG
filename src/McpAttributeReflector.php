<?php
/**
 * McpAttributeReflector - Extrait les outils MCP depuis les attributs PHP 8
 *
 * Analyse automatiquement les classes pour trouver les méthodes annotées avec
 * #[McpTool] et #[Schema], puis génère la configuration JSON MCP compatible.
 *
 * @author Olivier Ibanez & Claude
 * @version 1.0
 * @date 2025-11-28
 */

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class McpAttributeReflector
{
    /** @var array Cache des analyses pour éviter la re-réflection */
    private array $cache = [];

    /**
     * Analyse une classe et extrait tous les outils MCP
     *
     * @param string $className Nom complet de la classe à analyser
     * @return array Liste des outils MCP trouvés
     */
    public function analyzeClass(string $className): array
    {
        // Check cache
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $tools = [];

        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            error_log("[McpAttributeReflector] Class not found: $className - " . $e->getMessage());
            return [];
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Cherche l'attribut #[McpTool]
            $mcpToolAttrs = $method->getAttributes(McpTool::class);

            if (empty($mcpToolAttrs)) {
                continue; // Pas un outil MCP
            }

            try {
                $mcpTool = $mcpToolAttrs[0]->newInstance();

                $tools[] = [
                    'name' => $mcpTool->name,
                    'description' => $mcpTool->description ?? '',
                    'method' => $method->getName(),
                    'class' => $className,
                    'static' => $method->isStatic(),
                    'inputSchema' => $this->buildInputSchema($method)
                ];
            } catch (\Throwable $e) {
                error_log("[McpAttributeReflector] Error processing method {$method->getName()}: " . $e->getMessage());
            }
        }

        // Cache result
        $this->cache[$className] = $tools;

        return $tools;
    }

    /**
     * Construit le JSON Schema des paramètres d'une méthode
     *
     * @param ReflectionMethod $method
     * @return array JSON Schema compatible MCP
     */
    private function buildInputSchema(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $schema = $this->extractSchemaFromParam($param);

            // Ajoute la valeur par défaut si présente
            if ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                // Ne pas inclure null comme default pour les types nullable
                if ($defaultValue !== null) {
                    $schema['default'] = $defaultValue;
                }
            } else {
                // Pas de default = required (sauf si nullable)
                if (!$param->allowsNull()) {
                    $required[] = $paramName;
                }
            }

            $properties[$paramName] = $schema;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required
        ];
    }

    /**
     * Extrait le #[Schema] d'un paramètre ou déduit depuis le type PHP
     *
     * @param ReflectionParameter $param
     * @return array Schema du paramètre
     */
    private function extractSchemaFromParam(ReflectionParameter $param): array
    {
        $schemaAttrs = $param->getAttributes(Schema::class);

        if (!empty($schemaAttrs)) {
            // Utilise l'attribut #[Schema]
            try {
                $schema = $schemaAttrs[0]->newInstance();
                return $this->schemaToArray($schema);
            } catch (\Throwable $e) {
                error_log("[McpAttributeReflector] Error instantiating Schema for {$param->getName()}: " . $e->getMessage());
            }
        }

        // Fallback: déduit du type PHP
        return $this->inferSchemaFromType($param);
    }

    /**
     * Convertit un objet Schema en array
     *
     * @param Schema $schema
     * @return array
     */
    private function schemaToArray(Schema $schema): array
    {
        $result = [];

        // Type (obligatoire)
        if (property_exists($schema, 'type') && $schema->type !== null) {
            $result['type'] = $schema->type;
        }

        // Description
        if (property_exists($schema, 'description') && $schema->description !== null) {
            $result['description'] = $schema->description;
        }

        // Contraintes numériques
        if (property_exists($schema, 'minimum') && $schema->minimum !== null) {
            $result['minimum'] = $schema->minimum;
        }
        if (property_exists($schema, 'maximum') && $schema->maximum !== null) {
            $result['maximum'] = $schema->maximum;
        }

        // Contraintes string
        if (property_exists($schema, 'minLength') && $schema->minLength !== null) {
            $result['minLength'] = $schema->minLength;
        }
        if (property_exists($schema, 'maxLength') && $schema->maxLength !== null) {
            $result['maxLength'] = $schema->maxLength;
        }
        if (property_exists($schema, 'pattern') && $schema->pattern !== null) {
            $result['pattern'] = $schema->pattern;
        }

        // Enum
        if (property_exists($schema, 'enum') && $schema->enum !== null) {
            $result['enum'] = $schema->enum;
        }

        // Format (pour dates, emails, etc.)
        if (property_exists($schema, 'format') && $schema->format !== null) {
            $result['format'] = $schema->format;
        }

        return $result;
    }

    /**
     * Déduit le schema depuis le type PHP natif
     *
     * @param ReflectionParameter $param
     * @return array
     */
    private function inferSchemaFromType(ReflectionParameter $param): array
    {
        $type = $param->getType();

        if (!$type) {
            return ['type' => 'string'];
        }

        // Handle nullable types
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';

        $schema = match($typeName) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'object', 'stdClass' => ['type' => 'object'],
            'mixed' => ['type' => 'string'],
            default => ['type' => 'string']
        };

        return $schema;
    }

    /**
     * Génère la config complète pour le bridge MCP
     *
     * @param string $className Classe à analyser
     * @param string|null $socketPath Chemin du socket RPC (auto-généré si null)
     * @param string|null $moduleName Nom du module (déduit de la classe si null)
     * @return array Configuration du bridge
     */
    public function generateBridgeConfig(string $className, ?string $socketPath = null, ?string $moduleName = null): array
    {
        $tools = $this->analyzeClass($className);

        // Déduit le nom du module depuis la classe
        if ($moduleName === null) {
            try {
                $reflection = new ReflectionClass($className);
                $moduleName = strtolower($reflection->getShortName());
            } catch (ReflectionException $e) {
                $moduleName = strtolower($className);
            }
        }

        // Convention pour le socket path
        if ($socketPath === null) {
            $socketPath = "/var/run/cortex_{$moduleName}.sock";
        }

        // Mapping tool_name → action pour le RPC
        $toolActions = [];
        foreach ($tools as $tool) {
            // Convertit le nom du tool en action RPC
            // Ex: hrag_search → search, hrag_ingest → ingest
            $action = $tool['name'];
            if (str_starts_with($action, $moduleName . '_')) {
                $action = substr($action, strlen($moduleName) + 1);
            }
            $toolActions[$tool['name']] = $action;
        }

        return [
            'module' => $moduleName,
            'class' => $className,
            'socket' => $socketPath,
            'tools' => $tools,
            'tool_actions' => $toolActions,
            'tools_count' => count($tools),
            'generated_at' => date('c')
        ];
    }

    /**
     * Génère le JSON formaté pour debug/affichage
     *
     * @param string $className
     * @param string|null $socketPath
     * @return string JSON formaté
     */
    public function generateBridgeConfigJson(string $className, ?string $socketPath = null): string
    {
        $config = $this->generateBridgeConfig($className, $socketPath);
        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Analyse plusieurs classes et fusionne les configs
     *
     * @param array $classes Liste des classes à analyser
     * @return array Configurations par module
     */
    public function analyzeMultipleClasses(array $classes): array
    {
        $configs = [];

        foreach ($classes as $className => $options) {
            if (is_int($className)) {
                // Array simple: ['ClassName1', 'ClassName2']
                $className = $options;
                $options = [];
            }

            $socketPath = $options['socket'] ?? null;
            $moduleName = $options['module'] ?? null;

            $config = $this->generateBridgeConfig($className, $socketPath, $moduleName);
            $configs[$config['module']] = $config;
        }

        return $configs;
    }

    /**
     * Vide le cache d'analyse
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
