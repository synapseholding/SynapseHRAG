#!/bin/bash
# SynapseHRAG Demo API Examples
# No authentication required - public demo endpoints

API_BASE="https://hrag.synapsecorp.eu/api/modules/hrag"

echo "============================================"
echo "SynapseHRAG Public Demo API"
echo "============================================"
echo ""

# 1. System Info
echo "1. GET System Info"
echo "-------------------"
curl -s "${API_BASE}?action=demo_info" | jq .
echo ""

# 2. Live Statistics
echo "2. GET Live Statistics"
echo "-----------------------"
curl -s "${API_BASE}?action=demo_stats" | jq .
echo ""

# 3. Search Query
echo "3. POST Search Query"
echo "---------------------"
curl -s -X POST "${API_BASE}" \
  -d "action=demo_search&query=machine+learning" | jq .
echo ""

echo "============================================"
echo "Try your own queries!"
echo "Rate limit: 20 requests/minute"
echo "============================================"
