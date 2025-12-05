# SynapseHRAG - Community Edition

**Hierarchical RAG with Neural Activation**

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16+-blue.svg)](https://www.postgresql.org/)
[![Live Demo](https://img.shields.io/badge/Live_Demo-Try_Now!-green.svg)](https://hrag.synapsecorp.eu/api/modules/hrag?action=demo_info)
[![Version](https://img.shields.io/badge/version-0.1.0-orange.svg)](ROADMAP.md)

## What is HRAG?

HRAG (Hierarchical RAG) is a revolutionary approach to semantic search that mimics human brain organization with **three cognitive levels**:

```
STRATEGIC   -> Global vision, long-term context
TACTICAL    -> Derived insights, semantic patterns
OPERATIONAL -> Raw facts, atomic chunks
```

### Why HRAG?

Traditional RAG systems are **flat** - they lose hierarchical context and fail to understand semantic relationships. HRAG solves this by introducing:

- **Neural Architecture**: Documents as neurons, relations as synapses
- **Intent Routing**: Automatic semantic routing based on query intent
- **Hebbian Learning**: Synapses strengthen with usage
- **3x Faster**: Hierarchical filtering before vector search

## Live Demo API

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

> **Rate Limit**: 20 requests/minute (burst: 10)

## Community vs Startup

| Feature | Community | Startup |
|---------|-----------|---------|
| Search API | ✅ | ✅ |
| 3 cognitive levels | ✅ | ✅ |
| Hebbian learning | Manual (API call) | Auto + self-optimizing |
| Neurons limit | 1,000 | Unlimited |
| Ingest API | ❌ | ✅ |
| Support | Community | Priority + SLA |
| License | AGPL v3 | Commercial |

## Quick Start (Docker)

```bash
# Pull the Community image
docker pull synapsecorp/hrag:community

# Run with PostgreSQL
docker run -d \
  -e DATABASE_URL="postgresql://user:pass@host:5432/hrag" \
  -p 9090:9090 \
  synapsecorp/hrag:community
```

## Benchmarks (v1.2.0)

Quality improvements vs traditional RAG:

| Metric | Traditional | HRAG | Gain |
|--------|------------|------|------|
| **Precision@10** | 65% | **89%** | +37% |
| **Context Preservation** | 40% | **92%** | +130% |
| **Recall@10** | 59% | **82%** | +40% |

> *Benchmarks on Raspberry Pi 4 (ARM). Production on x86/GPU: < 50ms latency.*

## Startup Edition

Need full features?

- **Automatic Hebbian learning** - synapses self-optimize with usage
- **Ingest API** - write access to knowledge base
- **Unlimited neurons** - scale without limits
- **Priority support + SLA** - guaranteed response times
- **Commercial license** - deploy without AGPL constraints

**Pricing**: EUR 499/month

Contact: commercial@synapsecorp.eu

## License

**Community Edition** (this repo)
- Docker image, orchestration scripts, examples: **AGPL v3**
- Free to use, modify, and distribute
- Derivative works must remain AGPL v3

**HRAG Core Engine**
- Proprietary technology by SynapseCorp
- Commercial license required for Startup/Enterprise editions
- [Contact for licensing](mailto:commercial@synapsecorp.eu)

## Resources

- 📂 [Examples](examples/) - Ready-to-use API clients (Bash, Python)
- 🗺️ [Roadmap](ROADMAP.md) - Upcoming features and milestones
- 🌐 [Live Demo](https://hrag.synapsecorp.eu) - Try it now

## Credits

Developed by [SynapseCorp](https://synapsecorp.eu)

---

Website: https://hrag.synapsecorp.eu
