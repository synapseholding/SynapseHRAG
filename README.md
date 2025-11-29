# 🧠 SynapseHRAG - Community Edition

**Hierarchical RAG with Neural Activation**

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16+-blue.svg)](https://www.postgresql.org/)
[![Live Demo](https://img.shields.io/badge/🌐_Live_Demo-Try_Now!-green.svg)](https://hrag.synapsecorp.eu/api/modules/hrag?action=demo_info)

## 🎯 What is HRAG?

HRAG (Hierarchical RAG) is a revolutionary approach to semantic search that mimics human brain organization with **three cognitive levels**:

```
🎯 STRATEGIC  → Global vision, long-term context
📊 TACTICAL   → Derived insights, semantic patterns
📝 OPERATIONAL → Raw facts, atomic chunks
```

### Why HRAG?

Traditional RAG systems are **flat** - they lose hierarchical context and fail to understand semantic relationships. HRAG solves this by introducing:

- **🧠 Neural Architecture**: Documents as neurons, relations as synapses
- **🎯 Intent Routing**: Automatic semantic routing based on query intent
- **🔄 Hebbian Learning**: Synapses strengthen with usage
- **⚡ 3x Faster**: Hierarchical filtering before vector search

## 🌐 Live Demo API

**Try HRAG right now!** No signup, no API key required.

```bash
# System info
curl https://hrag.synapsecorp.eu/api/modules/hrag?action=demo_info

# Live statistics
curl https://hrag.synapsecorp.eu/api/modules/hrag?action=demo_stats

# Search the knowledge base
curl -X POST https://hrag.synapsecorp.eu/api/modules/hrag \
  -d "action=demo_search&query=machine+learning"
```

> ⚡ **Rate Limit**: 20 requests/minute (burst: 10)
>
> 🔬 **For Research Labs**: Need full access with neural search, vector embeddings, and MCP tools? Contact us under NDA: **research@synapsecorp.eu**

## 🔌 MCP Dynamic Bridge (World First!)

SynapseHRAG introduces the **first-ever dynamic MCP tool registration system**. AI assistants like Claude can use HRAG tools without server restarts.

### PHP 8 Attributes

```php
#[McpTool(name: 'hrag_search', description: 'Hierarchical RAG search')]
#[Schema([
    'query' => ['type' => 'string', 'required' => true],
    'limit' => ['type' => 'integer', 'default' => 10]
])]
public static function search(string $query, int $limit = 10): array
{
    // Your implementation
}
```

### How It Works

```
Module Start → PHP 8 Attributes Scan → WebSocket Register → Redis Store → MCP Ready
```

**Zero downtime** - Rename or add tools, they're instantly available to Claude!

## 🚀 Quick Start

### Installation

```bash
composer require synapsecorp/hrag
```

### Example

```php
<?php
require 'vendor/autoload.php';

use SynapseHRAG\Engine;

// Initialize
$hrag = new Engine($pdo);

// Search with intent
$results = $hrag->search(
    "How to optimize PostgreSQL?",
    intent: "technical"
);

// Results automatically include:
// - Strategic context
// - Tactical insights
// - Operational facts
```

## 📊 Benchmarks (v1.2.0)

Quality improvements vs traditional RAG:

| Metric | Traditional | HRAG | Gain |
|--------|------------|------|------|
| **Precision@10** | 65% | **89%** | +37% |
| **Context Preservation** | 40% | **92%** | +130% |
| **Recall@10** | 59% | **82%** | +40% |

HRAG Search Performance (Raspberry Pi 4):

| Metric | Value | Notes |
|--------|-------|-------|
| **Latency avg** | 441ms | With parallel UNION ALL queries |
| **P50** | 418ms | -19% vs sequential |
| **P95** | 625ms | |
| **Throughput** | 2.17 req/s | Stable |

MCP Dynamic Bridge Performance:

| Metric | Value |
|--------|-------|
| **tools/list** | **3.27ms** |
| **Hot reload** | Instant |

> ⚠️ *Benchmarks on Raspberry Pi 4 (ARM Cortex-A72). Production on x86/GPU estimated: < 50ms latency.*

See [benchmark suite](../tests/README.md) for full methodology.

## 📚 Documentation

- [Architecture Overview](https://gitlab.synapse.org/synapsecorp/docs/synapsehrag)
- [API Reference](https://gitlab.synapse.org/synapsecorp/docs/synapsehrag/-/blob/main/API.md)
- [Installation Guide](https://gitlab.synapse.org/synapsecorp/docs/synapsehrag/-/blob/main/INSTALL.md)

## 🏢 Enterprise Edition

Need more features?

- ✅ Multi-tenancy support
- ✅ Advanced neural routing
- ✅ Auto-optimization
- ✅ Priority support
- ✅ Commercial license

📧 Contact: commercial@synapsecorp.eu

## 🤝 Contributing

We welcome contributions! See our [Contributing Guide](../CONTRIBUTING.md).

## 📄 License

### Open Source (AGPL v3)
The **MCP Dynamic Bridge** components are open source:
- `McpAttributeReflector.php`
- `McpBridgeExtractor.php`
- `McpBridgeHandler.php`

You can use these to build your own MCP-enabled modules.

### Commercial License Required
The **HRAG Engine** (neural search, Hebbian learning, hierarchical architecture) requires a commercial license.

| Component | License |
|-----------|---------|
| MCP Bridge | AGPL v3 (free) |
| HRAG Engine | Commercial |
| Enterprise Features | Commercial |

📧 **Licensing**: commercial@synapsecorp.eu

## 🙏 Credits

Developed with ❤️ by [SynapseCorp](https://synapsecorp.eu) / Olivier Ibanez

Inspired by:
- Hierarchical neural architecture
- Hebbian Learning Theory
- Microsoft Access nested tables concept

---

⭐ **Star us on GitHub if you find HRAG useful!**

---

**Note**: This is the Community Edition. For full enterprise features, visit [GitLab](https://gitlab.synapse.org:8443/synapsecorp/components/synapsehrag).



