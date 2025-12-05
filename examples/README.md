# SynapseHRAG API Examples

Quick examples to interact with the public demo API.

## Prerequisites

- `curl` and `jq` for bash examples
- `python3` and `requests` for Python examples

## Usage

### Bash

```bash
chmod +x demo-api.sh
./demo-api.sh
```

### Python

```bash
pip install requests
python demo-api.py
```

## Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `?action=demo_info` | GET | System information |
| `?action=demo_stats` | GET | Live statistics |
| `?action=demo_search&query=...` | POST | Search knowledge base |

## Example Response (Search)

```json
{
  "success": true,
  "query": "machine learning",
  "results": {
    "strategic": [...],
    "tactical": [...],
    "operational": [...]
  },
  "latency_ms": 142,
  "levels_searched": 3
}
```

## Rate Limits

- **20 requests/minute** (burst: 10)
- No authentication required for demo

## Need More?

- **Startup Edition**: Unlimited queries, ingest API, Hebbian learning
- Contact: commercial@synapsecorp.eu
