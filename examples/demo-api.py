#!/usr/bin/env python3
"""
SynapseHRAG Demo API Client
No authentication required - public demo endpoints
"""

import requests
import json

API_BASE = "https://hrag.synapsecorp.eu/api/modules/hrag"


def demo_info():
    """Get system information"""
    response = requests.get(f"{API_BASE}?action=demo_info")
    return response.json()


def demo_stats():
    """Get live statistics"""
    response = requests.get(f"{API_BASE}?action=demo_stats")
    return response.json()


def demo_search(query: str):
    """
    Search the knowledge base

    Args:
        query: Search query string

    Returns:
        dict with results organized by cognitive level:
        - strategic: High-level concepts and vision
        - tactical: Derived insights and patterns
        - operational: Raw facts and atomic chunks
    """
    response = requests.post(API_BASE, data={
        "action": "demo_search",
        "query": query
    })
    return response.json()


if __name__ == "__main__":
    print("=" * 50)
    print("SynapseHRAG Public Demo API")
    print("=" * 50)

    # 1. System Info
    print("\n1. System Info:")
    print("-" * 30)
    info = demo_info()
    print(json.dumps(info, indent=2))

    # 2. Live Stats
    print("\n2. Live Statistics:")
    print("-" * 30)
    stats = demo_stats()
    print(json.dumps(stats, indent=2))

    # 3. Search
    print("\n3. Search 'machine learning':")
    print("-" * 30)
    results = demo_search("machine learning")
    print(json.dumps(results, indent=2))

    print("\n" + "=" * 50)
    print("Rate limit: 20 requests/minute")
    print("=" * 50)
